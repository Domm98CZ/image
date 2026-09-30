<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Animation;

use Domm98CZ\Image\Format\EncodedImage;
use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Format\Output\OutputFormatInterface;
use Domm98CZ\Image\Security\ValidatedInput;

interface AnimationCodecInterface
{
    public function format(): FormatName;

    public function canDecode(): bool;

    public function canEncode(): bool;

    public function decode(ValidatedInput $input): AnimatedImage;

    public function encode(AnimatedImage $animation, OutputFormatInterface $output): EncodedImage;
}
