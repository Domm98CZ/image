<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Unit\Metadata;

use Domm98CZ\Image\Exception\UnsupportedFormatException;
use Domm98CZ\Image\Format\Binary\BinaryString;
use Domm98CZ\Image\Format\EncodedImage;
use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Format\Header\HeaderProbe;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Metadata\ExifWriter;
use Domm98CZ\Image\Metadata\IptcWriter;
use Domm98CZ\Image\Metadata\MetadataEmbedder;
use Domm98CZ\Image\Metadata\MetadataExtractor;
use Domm98CZ\Image\Metadata\MetadataSet;
use Domm98CZ\Image\Metadata\XmpPacket;
use Domm98CZ\Image\Tests\Support\ImageBytes;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;

final class MetadataTest extends TestCase
{
    public function testXmpPacketRoundTripsEscapedValues(): void
    {
        $packet = new XmpPacket('cGF5bG9hZA==', 'c2ln', 'hmac-sha256', 'A & B <"Studio">', '© 2026 – ÿ');

        $parsed = XmpPacket::parse($packet->toXml());

        self::assertEquals($packet, $parsed);
    }

    /** @return iterable<string, array{string}> */
    public static function hostileXml(): iterable
    {
        yield 'external entity (XXE)' => ['<!DOCTYPE x [<!ENTITY e SYSTEM "file:///etc/passwd">]><x:xmpmeta xmlns:x="adobe:ns:meta/">&e;</x:xmpmeta>'];
        yield 'billion laughs' => ['<!DOCTYPE l [<!ENTITY a "aaaa"><!ENTITY b "&a;&a;&a;&a;">]><r>&b;</r>'];
        yield 'not xml' => ['<<<>>>'];
        yield 'empty' => [''];
        yield 'no watermark namespace' => ['<x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"><rdf:Description/></rdf:RDF></x:xmpmeta>'];
        yield 'oversized' => [str_repeat(' ', 1_048_577)];
    }

    #[DataProvider('hostileXml')]
    public function testHostileOrForeignXmpYieldsNothing(string $xml): void
    {
        self::assertNull(XmpPacket::parse($xml));
    }

    public function testExifWriterProducesReadableAsciiTags(): void
    {
        $tiff = (new ExifWriter())->write([ExifWriter::TAG_COPYRIGHT => '(c) Example', ExifWriter::TAG_ARTIST => 'Me']);
        $input = new BinaryString($tiff);

        self::assertSame("II*\0", substr($tiff, 0, 4));
        self::assertSame(2, $input->uint16LittleEndian(8));
        self::assertSame(ExifWriter::TAG_ARTIST, $input->uint16LittleEndian(10));
        self::assertSame("Me\0\0", substr($tiff, 18, 4));
        self::assertSame(ExifWriter::TAG_COPYRIGHT, $input->uint16LittleEndian(22));
        self::assertSame("(c) Example\0", substr($tiff, $input->uint32LittleEndian(30), 12));
    }

    public function testIptcWriterWrapsRecordsInAPhotoshopResource(): void
    {
        $resource = (new IptcWriter())->write([IptcWriter::BYLINE => 'Me']);

        self::assertStringStartsWith("8BIM\x04\x04", $resource);
        self::assertStringContainsString(IptcWriter::dataset(2, IptcWriter::BYLINE, 'Me'), $resource);
    }

    /** @return iterable<string, array{EncodedImage}> */
    public static function containers(): iterable
    {
        yield 'jpeg' => [new EncodedImage(ImageBytes::jpeg(10, 8), FormatName::Jpeg)];
        yield 'png' => [new EncodedImage(ImageBytes::png(10, 8), FormatName::Png)];
        yield 'webp simple lossless with alpha' => [new EncodedImage(ImageBytes::webpLossless(10, 8, true), FormatName::Webp)];
        yield 'webp extended' => [new EncodedImage(ImageBytes::webpExtended(10, 8, false), FormatName::Webp)];
    }

    #[DataProvider('containers')]
    public function testEmbedsReplacesAndExtractsMetadataWithoutTouchingTheImage(EncodedImage $image): void
    {
        $embedder = new MetadataEmbedder();
        $once = $embedder->embed($image, new MetadataSet('<first/>', "II*\0first"));
        $twice = $embedder->embed($once, new MetadataSet('<second/>', "II*\0second"));

        $extracted = (new MetadataExtractor())->extract($twice);
        $header = (new HeaderProbe())->probe(new BinaryString($twice->bytes));

        self::assertSame('<second/>', $extracted->xmp);
        self::assertSame("II*\0second", $extracted->exif);
        self::assertSame(1, substr_count($twice->bytes, 'second/>'));
        self::assertSame(0, substr_count($twice->bytes, 'first/>'));
        self::assertEquals(new Dimensions(10, 8), $header->canvas);
        self::assertSame((new HeaderProbe())->probe(new BinaryString($image->bytes))->hasAlpha, $header->hasAlpha);
    }

    public function testJpegKeepsJfifFirstAndCarriesIptc(): void
    {
        $embedded = (new MetadataEmbedder())->embed(new EncodedImage(ImageBytes::jpeg(4, 4), FormatName::Jpeg), new MetadataSet('<x/>', null, (new IptcWriter())->write([IptcWriter::BYLINE => 'Me'])));

        self::assertSame("\xFF\xD8\xFF\xE0", substr($embedded->bytes, 0, 4));
        self::assertNotNull((new MetadataExtractor())->extract($embedded)->iptc);
    }

    public function testWebpUpgradeAnnouncesMetadataInVp8xFlags(): void
    {
        $embedded = (new MetadataEmbedder())->embed(new EncodedImage(ImageBytes::webpLossy(10, 8), FormatName::Webp), new MetadataSet('<x/>', "II*\0"));
        $input = new BinaryString($embedded->bytes);

        self::assertSame('VP8X', $input->slice(12, 4));
        self::assertSame(0x0C, $input->uint8(20) & 0x0C);
        self::assertSame(strlen($embedded->bytes) - 8, $input->uint32LittleEndian(4));
    }

    /** @return iterable<string, array{string, FormatName}> */
    public static function unembeddableFormats(): iterable
    {
        yield 'gif' => [ImageBytes::gif(1, 1, [[1, 1]]), FormatName::Gif];
        yield 'avif' => [ImageBytes::avif([[1, 1]]), FormatName::Avif];
        yield 'heic' => [ImageBytes::heic(), FormatName::Heic];
    }

    #[DataProvider('unembeddableFormats')]
    public function testGifAvifAndHeicAreRejected(string $bytes, FormatName $format): void
    {
        $this->expectException(UnsupportedFormatException::class);

        (new MetadataEmbedder())->embed(new EncodedImage($bytes, $format), new MetadataSet('<x/>'));
    }

    public function testExtractorNeverFailsOnMalformedFiles(): void
    {
        $sample = (new MetadataEmbedder())->embed(new EncodedImage(ImageBytes::jpeg(10, 8), FormatName::Jpeg), new MetadataSet((new XmpPacket('YQ==', 'YQ==', 'crc32'))->toXml(), "II*\0"));
        mt_srand(11);
        for ($iteration = 0; $iteration < 2_000; ++$iteration) {
            $mutated = $sample->bytes;
            $mutated[mt_rand(2, strlen($mutated) - 1)] = chr(mt_rand(0, 255));
            try {
                $xmp = (new MetadataExtractor())->extract(new EncodedImage($mutated, FormatName::Jpeg))->xmp;
                XmpPacket::parse($xmp ?? '');
            } catch (Throwable $unexpected) {
                self::fail($unexpected::class . ': ' . $unexpected->getMessage());
            }
        }
        $this->addToAssertionCount(1);
    }
}
