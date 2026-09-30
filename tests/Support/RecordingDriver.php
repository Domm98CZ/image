<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Support;

use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Color\PixelBuffer;
use Domm98CZ\Image\Drawing\Font;
use Domm98CZ\Image\Driver\DriverInterface;
use Domm98CZ\Image\Driver\DriverName;
use Domm98CZ\Image\Driver\ImageHandleInterface;
use Domm98CZ\Image\Driver\Support;
use Domm98CZ\Image\Format\EncodedImage;
use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Format\Output\OutputFormatInterface;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\Geometry\Rectangle;
use Domm98CZ\Image\Operation\PrimitiveOperationInterface;
use Domm98CZ\Image\Security\ValidatedInput;

// Wraps a real driver and records the calls that move pixels, so a test can prove which driver did what.
final class RecordingDriver implements DriverInterface
{
    /** @var list<string> */
    public array $calls = [];

    public function __construct(
        private readonly DriverInterface $inner,
    ) {}

    public function name(): DriverName
    {
        return $this->inner->name();
    }

    public function decodingSupport(FormatName $format): Support
    {
        return $this->inner->decodingSupport($format);
    }

    public function encodingSupport(OutputFormatInterface $output): Support
    {
        return $this->inner->encodingSupport($output);
    }

    public function pixelAccess(): Support
    {
        return $this->inner->pixelAccess();
    }

    public function colorManagement(): Support
    {
        return $this->inner->colorManagement();
    }

    public function support(string $operation): Support
    {
        return $this->inner->support($operation);
    }

    public function decode(ValidatedInput $input): ImageHandleInterface
    {
        $this->calls[] = 'decode ' . $input->header->format->value;

        return $this->inner->decode($input);
    }

    public function create(Dimensions $dimensions, Color $background): ImageHandleInterface
    {
        $this->calls[] = 'create';

        return $this->inner->create($dimensions, $background);
    }

    public function copy(ImageHandleInterface $image): ImageHandleInterface
    {
        $this->calls[] = 'copy';

        return $this->inner->copy($image);
    }

    public function apply(ImageHandleInterface $image, PrimitiveOperationInterface $operation): ImageHandleInterface
    {
        $this->calls[] = 'apply ' . substr($operation::class, (int) strrpos($operation::class, '\\') + 1);

        return $this->inner->apply($image, $operation);
    }

    public function encode(ImageHandleInterface $image, OutputFormatInterface $output): EncodedImage
    {
        $this->calls[] = 'encode ' . $output->format()->value;

        return $this->inner->encode($image, $output);
    }

    public function dimensions(ImageHandleInterface $image): Dimensions
    {
        return $this->inner->dimensions($image);
    }

    public function colorAt(ImageHandleInterface $image, Point $point): Color
    {
        return $this->inner->colorAt($image, $point);
    }

    public function readPixels(ImageHandleInterface $image, Rectangle $area): PixelBuffer
    {
        $this->calls[] = 'read';

        return $this->inner->readPixels($image, $area);
    }

    public function measureText(string $text, Font $font): Rectangle
    {
        return $this->inner->measureText($text, $font);
    }

    public function writePixels(ImageHandleInterface $image, PixelBuffer $pixels, Point $origin): ImageHandleInterface
    {
        $this->calls[] = 'write';

        return $this->inner->writePixels($image, $pixels, $origin);
    }
}
