<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver;

use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Color\PixelBuffer;
use Domm98CZ\Image\Drawing\Font;
use Domm98CZ\Image\Format\EncodedImage;
use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Format\Output\OutputFormatInterface;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\Geometry\Rectangle;
use Domm98CZ\Image\Operation\PrimitiveOperationInterface;
use Domm98CZ\Image\Security\ValidatedInput;

interface DriverInterface
{
    public function name(): DriverName;

    public function decodingSupport(FormatName $format): Support;

    public function encodingSupport(OutputFormatInterface $output): Support;

    // How cheaply pixels move in and out as a PixelBuffer; prices steps that run in PHP over buffers.
    public function pixelAccess(): Support;

    // Whether decode converts an embedded ICC profile (wide gamut RGB, CMYK) to sRGB rather than ignoring it.
    public function colorManagement(): Support;

    /** @param class-string<PrimitiveOperationInterface> $operation */
    public function support(string $operation): Support;

    public function decode(ValidatedInput $input): ImageHandleInterface;

    public function create(Dimensions $dimensions, Color $background): ImageHandleInterface;

    public function copy(ImageHandleInterface $image): ImageHandleInterface;

    // May reuse and mutate $image: callers pass only handles they own (see Pipeline\Executor).
    public function apply(ImageHandleInterface $image, PrimitiveOperationInterface $operation): ImageHandleInterface;

    public function encode(ImageHandleInterface $image, OutputFormatInterface $output): EncodedImage;

    public function dimensions(ImageHandleInterface $image): Dimensions;

    public function colorAt(ImageHandleInterface $image, Point $point): Color;

    public function readPixels(ImageHandleInterface $image, Rectangle $area): PixelBuffer;

    // Ink box of the text relative to its baseline origin (y grows downward, so the top is negative).
    public function measureText(string $text, Font $font): Rectangle;

    // May reuse and mutate $image, like apply().
    public function writePixels(ImageHandleInterface $image, PixelBuffer $pixels, Point $origin): ImageHandleInterface;
}
