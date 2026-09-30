<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Watermark;

use Domm98CZ\Image\Exception\InvalidWatermarkException;
use Domm98CZ\Image\Format\EncodedImage;
use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Metadata\ExifWriter;
use Domm98CZ\Image\Metadata\IptcWriter;
use Domm98CZ\Image\Metadata\MetadataEmbedder;
use Domm98CZ\Image\Metadata\MetadataSet;
use Domm98CZ\Image\Metadata\XmpPacket;

// Signed payload in XMP (all of JPEG/PNG/WebP), creator/rights mirrored to EXIF and, for JPEG, IPTC.
final readonly class MetadataWatermark implements EncodedWatermarkInterface
{
    public const MAX_PAYLOAD_BYTES = 16_384;
    public const MAX_TEXT_LENGTH = 1_000;
    public const SCHEME_CRC32 = 'crc32';
    public const SCHEME_HMAC = 'hmac-sha256';

    public function __construct(
        public Payload $payload,
        public ?SecretKey $key = null,
        public ?string $creator = null,
        public ?string $rights = null,
    ) {
        if ($payload->length() > self::MAX_PAYLOAD_BYTES) {
            throw InvalidWatermarkException::payloadTooLarge($payload->length(), self::MAX_PAYLOAD_BYTES);
        }
        foreach ([$creator, $rights] as $text) {
            if ($text !== null && (!mb_check_encoding($text, 'UTF-8') || mb_strlen($text) > self::MAX_TEXT_LENGTH)) {
                throw InvalidWatermarkException::textTooLong(strlen($text), self::MAX_TEXT_LENGTH);
            }
        }
    }

    // The signature covers only the payload: metadata can be copied to another file, so treat it as a claim.
    public static function signature(Payload $payload, ?SecretKey $key): string
    {
        return $key === null ? pack('N', crc32($payload->bytes)) : $key->derive('metadata-watermark', $payload->bytes);
    }

    public function apply(EncodedImage $image): EncodedImage
    {
        $xmp = new XmpPacket(
            base64_encode($this->payload->bytes),
            base64_encode(self::signature($this->payload, $this->key)),
            $this->key === null ? self::SCHEME_CRC32 : self::SCHEME_HMAC,
            $this->creator,
            $this->rights,
        );
        $tags = array_filter([ExifWriter::TAG_ARTIST => $this->creator, ExifWriter::TAG_COPYRIGHT => $this->rights], static fn(?string $value): bool => $value !== null);
        $records = array_filter([IptcWriter::BYLINE => $this->creator, IptcWriter::COPYRIGHT_NOTICE => $this->rights], static fn(?string $value): bool => $value !== null);

        return (new MetadataEmbedder())->embed($image, new MetadataSet(
            $xmp->toXml(),
            $tags === [] ? null : (new ExifWriter())->write($tags),
            $records === [] || $image->format !== FormatName::Jpeg ? null : (new IptcWriter())->write($records),
        ));
    }
}
