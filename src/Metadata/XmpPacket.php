<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Metadata;

use DOMDocument;
use DOMElement;

// The library's XMP packet: Dublin Core creator/rights plus its own watermark namespace.
final readonly class XmpPacket
{
    public const WATERMARK_NAMESPACE = 'https://github.com/domm98cz/image/ns/watermark/1.0/';
    private const RDF_NAMESPACE = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';
    private const MAX_PARSE_BYTES = 1_048_576;

    public function __construct(
        public ?string $payload = null,
        public ?string $signature = null,
        public ?string $scheme = null,
        public ?string $creator = null,
        public ?string $rights = null,
    ) {}

    public function toXml(): string
    {
        $attributes = '';
        foreach (['payload' => $this->payload, 'signature' => $this->signature, 'scheme' => $this->scheme] as $name => $value) {
            if ($value !== null) {
                $attributes .= sprintf(' dmwm:%s="%s"', $name, self::escape($value));
            }
        }
        $properties = '';
        if ($this->creator !== null) {
            $properties .= '<dc:creator><rdf:Seq><rdf:li>' . self::escape($this->creator) . '</rdf:li></rdf:Seq></dc:creator>';
        }
        if ($this->rights !== null) {
            $properties .= '<dc:rights><rdf:Alt><rdf:li xml:lang="x-default">' . self::escape($this->rights) . '</rdf:li></rdf:Alt></dc:rights>';
        }

        return "<?xpacket begin=\"\u{FEFF}\" id=\"W5M0MpCehiHzreSzNTczkc9d\"?>"
            . '<x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF xmlns:rdf="' . self::RDF_NAMESPACE . '">'
            . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dmwm="' . self::WATERMARK_NAMESPACE . '"' . $attributes . '>'
            . $properties
            . '</rdf:Description></rdf:RDF></x:xmpmeta><?xpacket end="w"?>';
    }

    // Untrusted input: no network, no entity expansion, and any DTD is refused outright (XXE, billion laughs).
    public static function parse(string $xml): ?self
    {
        if ($xml === '' || strlen($xml) > self::MAX_PARSE_BYTES || stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) {
            return null;
        }
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            if (!$document->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT)) {
                return null;
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        foreach ($document->getElementsByTagNameNS(self::RDF_NAMESPACE, 'Description') as $description) {
            if (!$description->hasAttributeNS(self::WATERMARK_NAMESPACE, 'payload')) {
                continue;
            }

            return new self(
                $description->getAttributeNS(self::WATERMARK_NAMESPACE, 'payload'),
                $description->hasAttributeNS(self::WATERMARK_NAMESPACE, 'signature') ? $description->getAttributeNS(self::WATERMARK_NAMESPACE, 'signature') : null,
                $description->hasAttributeNS(self::WATERMARK_NAMESPACE, 'scheme') ? $description->getAttributeNS(self::WATERMARK_NAMESPACE, 'scheme') : null,
                self::firstListItem($description, 'creator'),
                self::firstListItem($description, 'rights'),
            );
        }

        return null;
    }

    private static function firstListItem(DOMElement $description, string $property): ?string
    {
        foreach ($description->getElementsByTagNameNS('http://purl.org/dc/elements/1.1/', $property) as $element) {
            foreach ($element->getElementsByTagNameNS(self::RDF_NAMESPACE, 'li') as $item) {
                return $item->textContent;
            }
        }

        return null;
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
