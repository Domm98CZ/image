<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Optimization\External;

use Domm98CZ\Image\Exception\ExternalToolException;

final readonly class ProcOpenProcessRunner implements ProcessRunnerInterface
{
    private const CHUNK_BYTES = 65_536;
    private const MAX_STDERR_BYTES = 65_536;
    // Upper bound on one stream_select() wait, so the timeout is checked at least this often.
    private const SELECT_TIMEOUT_MICROSECONDS = 200_000;
    private const SIGKILL = 9;

    public function run(array $command, string $stdin, float $timeoutSeconds, int $maxOutputBytes): ProcessResult
    {
        $tool = basename($command[0]);
        $error = null;
        set_error_handler(static function (int $severity, string $message) use (&$error): bool {
            $error ??= $message;

            return true;
        });
        try {
            // An array command makes proc_open exec the binary directly (no /bin/sh), so arguments cannot inject.
            $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        } finally {
            restore_error_handler();
        }
        if (!is_resource($process)) {
            throw ExternalToolException::cannotStart($tool, $error);
        }
        [$input, $output, $errors] = [$pipes[0], $pipes[1], $pipes[2]];
        foreach ([$input, $output, $errors] as $pipe) {
            stream_set_blocking($pipe, false);
        }

        $stdout = '';
        $stderr = '';
        $written = 0;
        $deadline = microtime(true) + $timeoutSeconds;
        try {
            while (true) {
                $remaining = $deadline - microtime(true);
                if ($remaining <= 0) {
                    throw ExternalToolException::timedOut($tool, $timeoutSeconds);
                }
                $read = array_values(array_filter([$output, $errors], static fn($pipe): bool => !feof($pipe)));
                $write = $written < strlen($stdin) ? [$input] : [];
                if ($read === [] && $write === []) {
                    break;
                }
                $except = null;
                if (stream_select($read, $write, $except, 0, (int) min(self::SELECT_TIMEOUT_MICROSECONDS, $remaining * 1_000_000)) === false) {
                    break;
                }
                foreach ($write as $pipe) {
                    // A tool may exit before reading all input; that "broken pipe" is its answer, not our error.
                    $chunk = @fwrite($pipe, substr($stdin, $written, self::CHUNK_BYTES));
                    $written = $chunk === false ? strlen($stdin) : $written + $chunk;
                    if ($written >= strlen($stdin)) {
                        fclose($input);
                    }
                }
                foreach ($read as $pipe) {
                    $chunk = (string) fread($pipe, self::CHUNK_BYTES);
                    if ($pipe === $output) {
                        $stdout .= $chunk;
                        if (strlen($stdout) > $maxOutputBytes) {
                            throw ExternalToolException::outputTooLarge($tool, $maxOutputBytes);
                        }
                    } elseif (strlen($stderr) < self::MAX_STDERR_BYTES) {
                        $stderr .= $chunk;
                    }
                }
                if ($stdin === '' && is_resource($input)) {
                    fclose($input);
                }
            }
        } catch (ExternalToolException $exception) {
            proc_terminate($process, self::SIGKILL);
            proc_close($process);

            throw $exception;
        }

        foreach ([$input, $output, $errors] as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        return new ProcessResult(proc_close($process), $stdout, $stderr);
    }
}
