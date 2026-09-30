<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Unit\Pipeline;

use Domm98CZ\Image\Color\CallbackPixelFilter;
use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Color\PixelBuffer;
use Domm98CZ\Image\Color\Profile\ColorProfile;
use Domm98CZ\Image\Color\Profile\RgbMatrixProfile;
use Domm98CZ\Image\Drawing\Line;
use Domm98CZ\Image\Drawing\Stroke;
use Domm98CZ\Image\Driver\DriverInterface;
use Domm98CZ\Image\Driver\DriverName;
use Domm98CZ\Image\Driver\DriverRegistry;
use Domm98CZ\Image\Driver\Support;
use Domm98CZ\Image\Exception\UnsupportedFormatException;
use Domm98CZ\Image\Exception\UnsupportedOperationException;
use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Format\Output\AvifOutput;
use Domm98CZ\Image\Format\Output\JpegOutput;
use Domm98CZ\Image\Format\Output\PngOutput;
use Domm98CZ\Image\Geometry\Anchor;
use Domm98CZ\Image\Geometry\Angle;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\Image;
use Domm98CZ\Image\Metadata\Orientation;
use Domm98CZ\Image\Operation\ApplyPixels;
use Domm98CZ\Image\Operation\Crop;
use Domm98CZ\Image\Operation\Draw;
use Domm98CZ\Image\Operation\Flip;
use Domm98CZ\Image\Operation\FlipDirection;
use Domm98CZ\Image\Operation\Grayscale;
use Domm98CZ\Image\Operation\OperationInterface;
use Domm98CZ\Image\Operation\Resize;
use Domm98CZ\Image\Operation\Rotate;
use Domm98CZ\Image\Operation\Thumbnail;
use Domm98CZ\Image\Pipeline\CostModel;
use Domm98CZ\Image\Pipeline\Plan;
use Domm98CZ\Image\Pipeline\Planner;
use Domm98CZ\Image\Pipeline\Source\BlankSource;
use Domm98CZ\Image\Pipeline\Source\DecodableSource;
use Domm98CZ\Image\Pipeline\Source\ImageSource;
use Domm98CZ\Image\Security\InputGuard;
use Domm98CZ\Image\Security\Limits;
use Domm98CZ\Image\Security\MemoryBudget;
use Domm98CZ\Image\Tests\Fake\FakeDriver;
use Domm98CZ\Image\Tests\Support\ImageBytes;
use PHPUnit\Framework\TestCase;

final class PlannerTest extends TestCase
{
    public function testAllNativeStaysOnThePreferredDriverWithoutTransfers(): void
    {
        [$gd, $imagick] = self::drivers();

        $plan = self::planner($gd, $imagick)->plan(self::jpeg(1000, 1000), [new Resize(new Dimensions(10, 10)), new Flip(FlipDirection::Both)], new PngOutput());

        self::assertSame('gd | gd gd | gd', self::route($plan));
        self::assertSame(0, $plan->transferCount());
    }

    public function testRunsEverythingOnTheDriverWithNativeSupportRatherThanPayingForAFallback(): void
    {
        $gd = new FakeDriver(DriverName::Gd, fallbackOperations: [Rotate::class]);
        $imagick = new FakeDriver(DriverName::Imagick);

        $plan = self::planner($gd, $imagick)->plan(self::jpeg(1000, 1000), [new Flip(FlipDirection::Both), new Rotate(Angle::clockwise(10))], new PngOutput());

        self::assertSame('imagick | imagick imagick | imagick', self::route($plan));
    }

    public function testImageBoundToOneDriverMovesOnceWhenTheFallbacksCostMoreThanTheTransfer(): void
    {
        $gd = new FakeDriver(DriverName::Gd, fallbackOperations: [Grayscale::class]);
        $imagick = new FakeDriver(DriverName::Imagick);
        $source = new ImageSource(self::imageOn($gd, 1000, 1000));
        $grayscale = new Grayscale();

        $plan = self::planner($gd, $imagick)->plan($source, [new Flip(FlipDirection::Both), $grayscale, $grayscale, new Flip(FlipDirection::Both)]);

        // Moving before or after the native flip costs the same; ties keep the path already on the target driver.
        self::assertSame('gd | imagick imagick imagick imagick', self::route($plan));
        self::assertSame(1, $plan->transferCount());
        // A transfer costs about as much as one PHP pass, so a single fallback step is cheaper done in place.
        self::assertSame('gd | gd gd gd', self::route(self::planner($gd, $imagick)->plan($source, [new Flip(FlipDirection::Both), $grayscale, new Flip(FlipDirection::Both)])));
    }

    public function testAnImageAlreadyOnGdKeepsAPixelStepAndItsEncodeThereRatherThanPayingForATransfer(): void
    {
        $gd = new FakeDriver(DriverName::Gd, pixelAccess: Support::PhpFallback);
        $imagick = new FakeDriver(DriverName::Imagick);
        $source = new ImageSource(self::imageOn($gd, 4000, 3000));
        $filter = new ApplyPixels(new CallbackPixelFilter(static fn(PixelBuffer $pixels): PixelBuffer => $pixels));

        self::assertSame('gd | gd | gd', self::route(self::planner($gd, $imagick)->plan($source, [$filter], new JpegOutput())));

        // Priced at a third of the PHP pass, as the transfer once was, the same case went across and back.
        $cheapTransfer = new Planner(new DriverRegistry([$gd, $imagick]), new CostModel(transferNanosPerPixel: 45.0));
        self::assertSame('gd | imagick | imagick', self::route($cheapTransfer->plan($source, [$filter], new JpegOutput())));
    }

    public function testSwitchesToAnotherDriverForAStepOnlyItCanRunAndBackForEncoding(): void
    {
        $gd = new FakeDriver(DriverName::Gd, unsupportedOperations: [Rotate::class]);
        $imagick = new FakeDriver(DriverName::Imagick, unencodable: [FormatName::Avif]);

        $plan = self::planner($gd, $imagick)->plan(self::jpeg(100, 100), [new Rotate(Angle::clockwise(10))], new AvifOutput());

        self::assertSame('imagick | imagick | gd', self::route($plan));
        self::assertSame(1, $plan->transferCount());
    }

    public function testTransfersAfterDownscalingRatherThanBefore(): void
    {
        $gd = new FakeDriver(DriverName::Gd);
        $imagick = new FakeDriver(DriverName::Imagick, unencodable: [FormatName::Png]);
        $source = new ImageSource(self::imageOn($imagick, 4000, 3000));

        $plan = self::planner($gd, $imagick)->plan($source, [new Resize(new Dimensions(100, 75)), new Flip(FlipDirection::Both)], new PngOutput());

        self::assertSame('imagick | imagick gd | gd', self::route($plan));
    }

    public function testDegradedEncoderIsAvoidedWhenALosslessDriverExists(): void
    {
        $gd = new FakeDriver(DriverName::Gd, fallbackOperations: [Rotate::class]);
        $imagick = new FakeDriver(DriverName::Imagick, degradedEncoding: [FormatName::Avif]);
        $operations = [new Rotate(Angle::clockwise(10))];

        $plan = self::planner($gd, $imagick)->plan(self::jpeg(500, 500), $operations, new AvifOutput());

        // The PHP fallback rotation on GD is cheaper than rotating natively on Imagick and paying a transfer to the lossless encoder.
        self::assertSame('gd | gd | gd', self::route($plan));
        self::assertSame('imagick | imagick | imagick', self::route(self::planner($gd, new FakeDriver(DriverName::Imagick))->plan(self::jpeg(500, 500), $operations, new AvifOutput())));
    }

    public function testDegradedEncoderIsUsedWhenNothingElseCanEncode(): void
    {
        $imagick = new FakeDriver(DriverName::Imagick, degradedEncoding: [FormatName::Avif]);

        $plan = self::planner($imagick)->plan(self::jpeg(10, 10), [], new AvifOutput());

        self::assertSame('imagick |  | imagick', self::route($plan));
    }

    public function testDegradedDecoderIsAvoidedWhenALosslessDriverExists(): void
    {
        $gd = new FakeDriver(DriverName::Gd, unsupportedOperations: [Rotate::class]);
        $imagick = new FakeDriver(DriverName::Imagick, degradedDecoding: [FormatName::Jpeg]);

        $plan = self::planner($gd, $imagick)->plan(self::jpeg(100, 100), [new Rotate(Angle::clockwise(10))]);

        self::assertSame('gd | imagick', self::route($plan));
    }

    public function testPixelStepsRunWhereBufferAccessIsCheap(): void
    {
        $gd = new FakeDriver(DriverName::Gd, pixelAccess: Support::PhpFallback);
        $imagick = new FakeDriver(DriverName::Imagick);
        $filter = new ApplyPixels(new CallbackPixelFilter(static fn(PixelBuffer $pixels): PixelBuffer => $pixels));

        $plan = self::planner($gd, $imagick)->plan(self::jpeg(1000, 1000), [$filter], new PngOutput());

        self::assertSame('imagick | imagick | imagick', self::route($plan));
    }

    public function testDrawingPrefersTheAntiAliasingDriverButStillWorksWithoutIt(): void
    {
        $gd = new FakeDriver(DriverName::Gd, degradedOperations: [Draw::class]);
        $imagick = new FakeDriver(DriverName::Imagick);
        $draw = new Draw([new Line(new Point(0, 0), new Point(1, 1), new Stroke(Color::black()))]);

        self::assertSame('imagick | imagick | imagick', self::route(self::planner($gd, $imagick)->plan(self::jpeg(100, 100), [$draw], new PngOutput())));
        self::assertSame('gd | gd', self::route(self::planner($gd)->plan(self::jpeg(100, 100), [$draw])));
    }

    public function testPixelStepsStayOnGdWhenItIsTheOnlyDriver(): void
    {
        $gd = new FakeDriver(DriverName::Gd, pixelAccess: Support::PhpFallback);
        $filter = new ApplyPixels(new CallbackPixelFilter(static fn(PixelBuffer $pixels): PixelBuffer => $pixels));

        self::assertSame('gd | gd', self::route(self::planner($gd)->plan(self::jpeg(10, 10), [$filter])));
    }

    public function testCompositeCostsAsMuchAsItsWorstSupportedPrimitive(): void
    {
        $gd = new FakeDriver(DriverName::Gd, unsupportedOperations: [Crop::class]);
        $imagick = new FakeDriver(DriverName::Imagick);

        $plan = self::planner($gd, $imagick)->plan(self::jpeg(10, 10), [new Thumbnail(new Dimensions(5, 5), Anchor::Center)]);

        self::assertSame('imagick | imagick', self::route($plan));
    }

    public function testDecodesOnADriverThatSupportsTheFormat(): void
    {
        $gd = new FakeDriver(DriverName::Gd, undecodable: [FormatName::Jpeg]);
        $imagick = new FakeDriver(DriverName::Imagick, unsupportedOperations: [Rotate::class]);

        $plan = self::planner($gd, $imagick)->plan(self::jpeg(10, 10), [new Rotate(Angle::clockwise(10))]);

        self::assertSame('imagick | gd', self::route($plan));
    }

    public function testExplainsUndecodableFormat(): void
    {
        $this->expectExceptionObject(UnsupportedFormatException::noDriverCanDecode(FormatName::Jpeg));

        self::planner(new FakeDriver(undecodable: [FormatName::Jpeg]))->plan(self::jpeg(10, 10), []);
    }

    public function testExplainsUnsupportedOperation(): void
    {
        $this->expectExceptionObject(UnsupportedOperationException::noDriverSupports(Rotate::class, [DriverName::Gd]));

        self::planner(new FakeDriver(unsupportedOperations: [Rotate::class]))->plan(self::jpeg(10, 10), [new Rotate(Angle::clockwise(1))]);
    }

    public function testExplainsUnencodableOutput(): void
    {
        $this->expectExceptionObject(UnsupportedFormatException::noDriverCanEncode(FormatName::Avif));

        self::planner(new FakeDriver(unencodable: [FormatName::Avif]))->plan(new BlankSource(new Dimensions(1, 1), Color::black()), [], new AvifOutput());
    }

    public function testRequiredPrimitivesAreUniqueAndIncludeCompositeRequirements(): void
    {
        /** @var list<OperationInterface> $operations */
        $operations = [new Resize(new Dimensions(1, 1)), new Thumbnail(new Dimensions(1, 1)), new Resize(new Dimensions(2, 2))];

        self::assertSame([Resize::class, Crop::class], Planner::requiredPrimitives($operations));
    }

    /** @return array{FakeDriver, FakeDriver} */
    public function testAWideGamutProfileMovesTheDecodeToTheColorManagedDriverAndOnlyThen(): void
    {
        $gd = new FakeDriver(DriverName::Gd, colorManagement: Support::Degraded);
        $imagick = new FakeDriver(DriverName::Imagick, unsupportedOperations: [Flip::class]);
        $operations = [new Flip(FlipDirection::Both)];

        $linear = ColorProfile::tryParse(RgbMatrixProfile::linearSrgbPrimaries()->bytes());
        $srgb = ColorProfile::tryParse(RgbMatrixProfile::srgb()->bytes());

        self::assertSame('imagick | gd | gd', self::route(self::planner($gd, $imagick)->plan(self::jpeg(100, 100, $linear), $operations, new PngOutput())));
        self::assertSame('gd | gd | gd', self::route(self::planner($gd, $imagick)->plan(self::jpeg(100, 100, $srgb), $operations, new PngOutput())));
        self::assertSame('gd | gd | gd', self::route(self::planner($gd, $imagick)->plan(self::jpeg(100, 100), $operations, new PngOutput())));
    }

    public function testWithoutAColorManagedDriverTheProfileIsIgnoredRatherThanFailing(): void
    {
        $gd = new FakeDriver(DriverName::Gd, colorManagement: Support::Degraded);
        $source = self::jpeg(100, 100, ColorProfile::tryParse(RgbMatrixProfile::linearSrgbPrimaries()->bytes()));

        self::assertSame('gd | gd', self::route(self::planner($gd)->plan($source, [new Flip(FlipDirection::Both)])));
    }

    public function testSupportLevelsCombineToTheWorse(): void
    {
        self::assertSame(Support::Degraded, Support::Native->worse(Support::Degraded));
        self::assertSame(Support::Degraded, Support::Degraded->worse(Support::PhpFallback));
        self::assertSame(Support::None, Support::PhpFallback->worse(Support::None));
        self::assertSame(Support::Native, Support::Native->worse(Support::Native));
    }

    private static function drivers(): array
    {
        return [new FakeDriver(DriverName::Gd), new FakeDriver(DriverName::Imagick)];
    }

    private static function planner(DriverInterface ...$drivers): Planner
    {
        return new Planner(new DriverRegistry(array_values($drivers)));
    }

    // "source | step drivers... | encoder" for readable assertions.
    private static function route(Plan $plan): string
    {
        $steps = array_map(static fn($step): string => $step->driver->name()->value, $plan->steps);
        $route = $plan->sourceDriver->name()->value . ' | ' . implode(' ', $steps);

        return $plan->encoder === null ? $route : $route . ' | ' . $plan->encoder->name()->value;
    }

    private static function jpeg(int $width, int $height, ?ColorProfile $profile = null): DecodableSource
    {
        return new DecodableSource((new InputGuard(Limits::default()))->inspect(ImageBytes::jpeg($width, $height), MemoryBudget::unlimited()), Orientation::TopLeft, $profile);
    }

    private static function imageOn(FakeDriver $driver, int $width, int $height): Image
    {
        return new Image($driver->create(new Dimensions($width, $height), Color::black()), $driver, Orientation::TopLeft, null);
    }
}
