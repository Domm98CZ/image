<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Unit\Operation;

use Domm98CZ\Image\Exception\InvalidDimensionsException;
use Domm98CZ\Image\Geometry\Anchor;
use Domm98CZ\Image\Geometry\Angle;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\Geometry\Rectangle;
use Domm98CZ\Image\Metadata\Orientation;
use Domm98CZ\Image\Operation\AnchoredCrop;
use Domm98CZ\Image\Operation\AutoOrient;
use Domm98CZ\Image\Operation\CompositeOperationInterface;
use Domm98CZ\Image\Operation\Crop;
use Domm98CZ\Image\Operation\ExpansionContext;
use Domm98CZ\Image\Operation\FitInside;
use Domm98CZ\Image\Operation\FitOutside;
use Domm98CZ\Image\Operation\OperationInterface;
use Domm98CZ\Image\Operation\Resize;
use Domm98CZ\Image\Operation\Rotate;
use Domm98CZ\Image\Operation\Scale;
use Domm98CZ\Image\Operation\ScaleToHeight;
use Domm98CZ\Image\Operation\ScaleToWidth;
use Domm98CZ\Image\Operation\Thumbnail;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CompositeOperationTest extends TestCase
{
    /** @return iterable<string, array{CompositeOperationInterface, Dimensions, list<OperationInterface>}> */
    public static function expansions(): iterable
    {
        $image = new Dimensions(1600, 900);
        yield 'scale half' => [new Scale(0.5), $image, [new Resize(new Dimensions(800, 450))]];
        yield 'scale by one is a no-op' => [new Scale(1.0), $image, []];
        yield 'scale to width' => [new ScaleToWidth(400), $image, [new Resize(new Dimensions(400, 225))]];
        yield 'scale to height' => [new ScaleToHeight(90), $image, [new Resize(new Dimensions(160, 90))]];
        yield 'fit inside shrinks' => [new FitInside(new Dimensions(400, 400)), $image, [new Resize(new Dimensions(400, 225))]];
        yield 'fit inside upscales on request' => [new FitInside(new Dimensions(3200, 3200), true), $image, [new Resize(new Dimensions(3200, 1800))]];
        yield 'fit outside shrinks' => [new FitOutside(new Dimensions(400, 400)), $image, [new Resize(new Dimensions(711, 400))]];
        yield 'fit outside upscales on request' => [new FitOutside(new Dimensions(1800, 1800), true), $image, [new Resize(new Dimensions(3200, 1800))]];
        yield 'anchored crop center' => [new AnchoredCrop(new Dimensions(400, 400)), $image, [new Crop(new Rectangle(new Point(600, 250), new Dimensions(400, 400)))]];
        yield 'anchored crop clamps to image' => [new AnchoredCrop(new Dimensions(2000, 400), Anchor::Top), $image, [new Crop(new Rectangle(new Point(0, 0), new Dimensions(1600, 400)))]];
        yield 'anchored crop of whole image is a no-op' => [new AnchoredCrop(new Dimensions(1600, 900)), $image, []];
        yield 'thumbnail' => [
            new Thumbnail(new Dimensions(300, 300), Anchor::Left),
            $image,
            [new FitOutside(new Dimensions(300, 300), true), new AnchoredCrop(new Dimensions(300, 300), Anchor::Left)],
        ];
    }

    /** @param list<OperationInterface> $expected */
    #[DataProvider('expansions')]
    public function testExpandsIntoExactOperations(CompositeOperationInterface $composite, Dimensions $image, array $expected): void
    {
        self::assertEquals($expected, $composite->expand(new ExpansionContext($image)));
    }

    public function testAutoOrientExpandsIntoOrientationCorrections(): void
    {
        $expanded = (new AutoOrient())->expand(new ExpansionContext(new Dimensions(10, 20), Orientation::RightTop));

        self::assertEquals([new Rotate(Angle::clockwise(90))], $expanded);
    }

    /** @return iterable<string, array{CompositeOperationInterface, list<string>}> */
    public static function requirements(): iterable
    {
        yield 'scale' => [new Scale(2.0), [Resize::class]];
        yield 'fit inside' => [new FitInside(new Dimensions(1, 1)), [Resize::class]];
        yield 'anchored crop' => [new AnchoredCrop(new Dimensions(1, 1)), [Crop::class]];
        yield 'thumbnail' => [new Thumbnail(new Dimensions(1, 1)), [Resize::class, Crop::class]];
    }

    /** @param list<string> $expected */
    #[DataProvider('requirements')]
    public function testDeclaresRequiredPrimitives(CompositeOperationInterface $composite, array $expected): void
    {
        self::assertSame($expected, $composite->requiredPrimitives());
    }

    public function testRejectsInvalidParameters(): void
    {
        $rejected = 0;
        foreach ([static fn() => new Scale(0.0), static fn() => new Scale(NAN), static fn() => new ScaleToWidth(0), static fn() => new ScaleToHeight(-1)] as $create) {
            try {
                $create();
            } catch (InvalidDimensionsException) {
                ++$rejected;
            }
        }

        self::assertSame(4, $rejected);
    }
}
