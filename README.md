# domm98cz/image

[![Build Status](https://img.shields.io/github/actions/workflow/status/Domm98CZ/image/ci.yml?branch=main)](https://github.com/Domm98CZ/image/actions/workflows/ci.yml) [![Latest Version](https://img.shields.io/packagist/v/Domm98CZ/image)](https://packagist.org/packages/Domm98CZ/image) [![PHP Version](https://img.shields.io/packagist/php-v/Domm98CZ/image)](https://packagist.org/packages/Domm98CZ/image) [![License](https://img.shields.io/github/license/Domm98CZ/image)](LICENSE) [![Downloads](https://img.shields.io/packagist/dt/Domm98CZ/image)](https://packagist.org/packages/Domm98CZ/image)

Image manipulation for PHP 8.2+: GD and Imagick behind one immutable, fluent API.

- **Geometry:** resize, crop, fit and thumbnail, rotate, flip, EXIF auto-orientation.
- **Color and pixels:** filters and color adjustments, pixel access, drawing and text, alpha everywhere.
- **Animation:** animated GIF (any driver) and animated WebP (Imagick).
- **Formats:** JPEG, PNG, GIF, WebP and AVIF, with typed encoder options, optimization and a "smallest of" encoder.
  HEIC/HEIF input (Imagick only, decode only) reads iPhone photo uploads without a separate conversion step.
- **Watermarks:** three independent kinds - visible, steganographic (LSB / DCT) and metadata (XMP / EXIF / IPTC) -
  each optionally keyed and signed.
- **Color management:** embedded ICC profiles (Display P3, Adobe RGB, CMYK) are converted to sRGB.
- **PSR-7 interop:** reads from any `StreamInterface`; writes streams and responses through your PSR-17 factory.
- **Security:** every input is treated as hostile. Size caps, format sniffing and a decompression-bomb guard
  run before anything is decoded.

**License:** MIT.

## Install

```sh
composer require domm98cz/image
```

Requires PHP 8.2 or newer, `ext-mbstring`, `ext-dom` and `ext-zlib` (declared in `composer.json`, present
in most default PHP installs), and `ext-gd` and/or `ext-imagick`. Both image extensions are optional on
their own: GD alone or Imagick alone runs the whole API, and with both installed each step runs on whichever
driver does it better (see [Drivers](#drivers) for what differs). `psr/http-message`
and `psr/http-factory` are interface-only dependencies - any PSR-17 implementation works for output, e.g.
`nyholm/psr7` or `guzzlehttp/psr7`. Optional: the `pngquant`, `jpegtran`, `cwebp` and `gifsicle` binaries,
for the matching `Optimization\External\*` adapters.

## Quick start

```php
use Domm98CZ\Image\ImageBuilder;
use Domm98CZ\Image\Geometry\{Anchor, Dimensions};

ImageBuilder::open('photo.jpg')
    ->autoOrient()
    ->thumbnail(new Dimensions(300, 300), Anchor::Center)
    ->save('thumb.webp');                     // format from the extension
```

Every fluent call returns a new builder and nothing is decoded until a terminal call. Input is still validated
at `open*()`, so a hostile or broken file fails there, not later at `save()`. Runnable scripts for every feature
below are in [`examples/`](examples/); each one generates its own input, so no image file is needed.

## Opening images

Applications should create one configured `ImageFactory` (it is DI-friendly). The static `ImageBuilder::open*()`
shortcuts use the default configuration.

```php
use Domm98CZ\Image\{Configuration, ImageFactory};
use Domm98CZ\Image\Security\Limits;

$images = new ImageFactory(new Configuration(Limits::default()->withMaxPixels(80_000_000)));

$images->open('/path/photo.jpg');           // local paths only; stream wrappers (phar://, http://) are refused
$images->openBytes($bytes);
$images->openStream($request->getBody());   // any PSR-7 stream, read in chunks under the byte cap
$images->create(new Dimensions(800, 600), Color::white());
$images->from($image);                      // continue from an already processed Image, even one another factory built

$base = $images->open('photo.jpg')->autoOrient()->toImage();   // decode once...
foreach ([320, 640, 1280] as $width) {                          // ...derive many variants
    $images->from($base)->fitInside(new Dimensions($width, $width))->save("photo-$width.avif");
}

$images->openBytes($iphoneUpload)->fitInside(new Dimensions(1600, 1600))->save('photo.webp');  // HEIC in, WebP out
```

HEIC/HEIF (the format iPhones save photos as) is decode-only and Imagick-only: GD has no HEIC codec at all,
and this library never writes HEIC (no encoder is registered, for the same patent/licensing reasons most
web tooling re-encodes it to WebP or AVIF instead). EXIF orientation and embedded metadata are not read yet
for HEIC - `autoOrient()` is a no-op on it, same as today's AVIF.

## Operations

| Call | Does |
|---|---|
| `resize(Dimensions, Interpolation $interpolation = Interpolation::Lanczos)` | exact size (aspect ratio not kept) |
| `scale(float)`, `scaleToWidth(int)`, `scaleToHeight(int)` | proportional scaling, each takes the same `Interpolation` |
| `fitInside(Dimensions, upscale: false)` | largest size that fits in the box |
| `fitOutside(Dimensions, upscale: false)` | smallest size that covers the box |
| `thumbnail(Dimensions, Anchor, upscale: true)` | cover and crop to exactly that size |
| `crop(Rectangle)`, `cropAnchored(Dimensions, Anchor)` | cut out a region |
| `pad(int $left, int $top, int $right, int $bottom, ?Color)`, `extend(...)` | grow the canvas, filling with a background color (transparent by default) |
| `border(int $size, ?Color)` | `pad()` with the same size on all four sides |
| `trim()` | remove a uniform-colored outer border |
| `paste(Image, Point, float $opacity = 1.0, BlendMode)`, `insert(...)` | composite another image at a position |
| `rotate(Angle, ?Color $background)`, `flip(FlipDirection)`, `autoOrient()` | orientation (angles are clockwise) |
| `grayscale()`, `invert()`, `brightness(int)`, `contrast(int)`, `gamma(float)` | adjustments |
| `blur(float $sigma)`, `sharpen(float)`, `colorize(Color, float)` | filters |
| `opacity(float)`, `sepia()`, `hueSaturation(float $hue, float $saturation)`, `pixelate(int $size)` | more filters, over the whole image |
| `roundedCorners(int $radius)`, `mask(Image)` | clear or multiply the alpha channel |
| `draw(Canvas\|Closure)` | shapes and text (below) |
| `applyPixels(PixelFilterInterface\|Closure, ?Rectangle)` | your own filter over a `PixelBuffer` |
| `watermark(WatermarkInterface)` | visible, steganographic or metadata watermark |
| `apply(OperationInterface)` | any operation value, including your own |

Operations are plain immutable values (`new Resize(...)`, `new Blur(...)`), so they can be stored and reused
with `applyAll()`.

`Interpolation` (`Nearest`, `Bilinear`, `Bicubic`, `Lanczos`) picks the resampling filter for every resize-family
call. Imagick maps it 1:1 to `Imagick::FILTER_*`; GD only distinguishes `Nearest` (`imagecopyresized`) from
everything else (`imagecopyresampled`), so `Bilinear`/`Bicubic`/`Lanczos` render identically on GD.

`trim()`'s output size depends on pixel content, so the planner cannot forecast it: `resultingDimensions()`
conservatively reports the input size unchanged, which only affects cost estimation for steps chained after
`trim()`, never correctness.

## Output

```php
use Domm98CZ\Image\Format\Output\{AvifOutput, GifOutput, JpegOutput, PngOutput, WebpOutput};

$builder = $images->open('photo.jpg')->fitInside(new Dimensions(1600, 1600));

$builder->save('out.jpg');                                   // format from the extension, default options
$builder->save('out.jpg', new JpegOutput(quality: 82, progressive: true));
$encoded = $builder->encode(new WebpOutput(quality: 80));    // EncodedImage: ->bytes, ->mimeType(), ->fileExtension()
$uri = $builder->toDataUri(new PngOutput());                 // "data:image/png;base64,..."

// PSR-7: bring your own PSR-17 factories (here nyholm/psr7).
$psr17 = new \Nyholm\Psr7\Factory\Psr17Factory();
$stream = $builder->toStream($psr17, new AvifOutput(quality: 50));
return $builder->toResponse($psr17, $psr17, new WebpOutput());   // Content-Type, Content-Length, nosniff
```

Some details:

- **`save()` is atomic.** It writes a temporary file next to the target and renames it, so readers never see
  half an image. An existing file keeps its permissions.
- **Extensions are checked.** A path whose known extension contradicts the requested format
  (`save('a.png', new JpegOutput())`) is refused, so no file is served with the wrong type.
- **Encoder options:**
  - `JpegOutput(quality 1–100, progressive, background)`. The background replaces transparency.
  - `PngOutput(compressionLevel 0–9, keepAlphaChannel)`. A fully opaque image is written without an alpha
    channel (smaller, same pixels); `keepAlphaChannel: true` forces RGBA output anyway.
  - `WebpOutput(quality, lossless)`.
  - `AvifOutput(quality, speed)`.
  - `GifOutput`.
- **Metadata** is stripped on encode unless you add a metadata watermark.

### Optimization and the smallest encoding

```php
use Domm98CZ\Image\Optimization\MetadataStripper;
use Domm98CZ\Image\Optimization\External\PngquantOptimizer;
use Domm98CZ\Image\Optimization\External\JpegtranOptimizer;
use Domm98CZ\Image\Optimization\External\CwebpOptimizer;
use Domm98CZ\Image\Optimization\External\GifsicleOptimizer;

$png = $images->open('chart.png')
    ->optimize(new MetadataStripper())                        // drops EXIF/XMP/comments, keeps the ICC profile
    ->optimize(new PngquantOptimizer('/usr/bin/pngquant'))    // PNG only; no shell, timeout, output cap
    ->encode(new PngOutput());

$jpeg = $images->open('photo.jpg')->encode(new JpegOutput());
$smaller = (new JpegtranOptimizer('/usr/bin/jpegtran'))->optimize($jpeg);   // lossless: progressive + optimized Huffman

$webp = $images->open('photo.jpg')->encode(new WebpOutput());
$smallerWebp = (new CwebpOptimizer('/usr/bin/cwebp', quality: 80))->optimize($webp);   // lossy re-encode

$gif = $images->open('banner.gif')->encode(new GifOutput());
$smallerGif = (new GifsicleOptimizer('/usr/bin/gifsicle'))->optimize($gif);   // lossless; animation-safe

$best = $images->open('photo.jpg')
    ->fitInside(new Dimensions(1200, 1200))
    ->encodeSmallest(new AvifOutput(quality: 50), new WebpOutput(quality: 80), new JpegOutput(quality: 82));
```

Optimizers run after encoding and before metadata watermarks, so they cannot strip the watermark. Every
`Optimization\External\*` adapter follows the same rules: an argument vector run without a shell, a timeout,
an output-size cap, and re-validation of the tool's output (format, dimensions, and for `GifsicleOptimizer`
frame count) before trusting it - the external binary is treated as untrusted, same as any other input. All
of them keep the original bytes if the tool's result is not smaller.

## Pixels, drawing and text

```php
use Domm98CZ\Image\Color\{Color, PixelBuffer};
use Domm98CZ\Image\Drawing\{Canvas, Font, Stroke};
use Domm98CZ\Image\Geometry\{Point, Rectangle};

$image = $images->open('photo.png')->toImage();
$image->colorAt(new Point(10, 10));                  // Color (RGBA)
$buffer = $image->pixels(new Rectangle(new Point(0, 0), new Dimensions(64, 64)));   // packed RGBA8

$font = new Font('/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf', 28);   // explicit font, nothing bundled
$images->open('photo.png')
    ->draw(fn(Canvas $canvas): Canvas => $canvas
        ->rectangle(new Rectangle(new Point(10, 10), new Dimensions(200, 60)), Color::rgba(0, 0, 0, 0.5))
        ->line(new Point(10, 80), new Point(210, 80), new Stroke(Color::white(), 2))
        ->text('Hello', new Point(20, 52), $font, Color::white()))
    ->applyPixels(fn(PixelBuffer $pixels): PixelBuffer => $pixels->withBytes(implode('', array_reverse(str_split($pixels->bytes, 4)))))
    ->save('out.png');
```

Characters the font has no glyph for are drawn as U+FFFD (the replacement character, or `?` when the font lacks
that too) on every driver, so text looks the same whichever driver renders it. Text is always drawn literally: `&amp;`
stays `&amp;`. GD cannot reach glyphs beyond the Basic Multilingual Plane (emoji, historic scripts) even when the font
has them, so it draws the same placeholder there while Imagick draws the glyph. Lines separated by `"\n"` are stacked
1.2 em apart (1.2 x `Font::$size`, rotated along with the text) on every driver; for another line pitch draw each line
with its own `text()` call.

Charts need nothing beyond these primitives: [`examples/line-chart.php`](examples/line-chart.php) and
[`examples/bar-chart.php`](examples/bar-chart.php) build them from lines, polygons, ellipses, rectangles and text.
Because GD draws thick strokes and filled shapes with jagged edges (see the driver table below), both draw at three
times the final size and shrink the result with `resize()`, which anti-aliases the edges on either driver.

## Analysis

Read-only queries over the decoded pixels, never touched by any driver-specific handler:

```php
use Domm98CZ\Image\Analysis\PerceptualHash;

$image = $images->open('photo.jpg')->toImage();

$image->hasAlpha();                 // bool; isOpaque() is its inverse
$image->averageColor();             // Color: mean of every pixel
$image->dominantColor();            // Color: the most populous cluster, median-cut over up to 4096 samples
$image->palette(5);                 // list<Color>, most populous cluster first
$image->histogram();                // Histogram: ->red/->green/->blue/->alpha, 256 buckets each

$image->blurhash();                 // string, blurhash.org format (default 4x3 components)
$image->perceptualHash();           // string, 16 hex chars (dHash, 64 bits)
PerceptualHash::hammingDistance($image->perceptualHash(), $other->perceptualHash());
```

`dominantColor()`/`palette()` use median-cut, splitting the widest-range channel at its population
median; a solid-color image always yields exactly one cluster. `blurhash()` follows the standard
[blurhash.org](https://blurhash.io) algorithm (alpha is not part of that format). `perceptualHash()`
is a difference hash: robust to recompression and minor resizing, not to rotation or cropping.

## Animations

GIF works on any driver; animated WebP needs Imagick. The same fluent operations apply to every frame.

```php
use Domm98CZ\Image\AnimationBuilder;
use Domm98CZ\Image\Animation\Frame;

AnimationBuilder::open('banner.gif')
    ->fitInside(new Dimensions(320, 320))
    ->withPlayCount(0)              // 0 = loop forever
    ->save('banner-small.webp');

$images->animation([new Frame($first, 100), new Frame($second, 100)])->save('blink.gif');
```

Animation builders offer the same terminal calls as images: `encode`, `encodeSmallest`, `save`, `toStream`,
`toResponse` and `toDataUri`, and accept stored operations through `applyAll()` just like image builders.
Unlike `ImageBuilder`, `openAnimation*()` decodes every frame at open time; only the operations are deferred.

## Watermarks

All three watermark kinds use the same `->watermark()` call:

```php
use Domm98CZ\Image\Watermark\{VisibleWatermark, MetadataWatermark, MetadataWatermarkReader, Payload, SecretKey};
use Domm98CZ\Image\Watermark\Steganography\{DctWatermark, DctReader};
use Domm98CZ\Image\Geometry\Position;

$key = new SecretKey($_ENV['WATERMARK_KEY']);        // >= 16 bytes, never printed or serialized
$logo = $images->text('© ACME', $font, Color::white(), padding: 4);

$jpeg = $images->openStream($upload)
    ->fitInside(new Dimensions(1600, 1600))
    ->watermark(new VisibleWatermark($logo, Position::inset(Anchor::BottomRight, 16), opacity: 0.6))
    ->watermark(new DctWatermark(Payload::text('customer-77'), $key))    // survives JPEG re-compression
    ->watermark(new MetadataWatermark(Payload::text('asset-9912'), $key, creator: 'ACME', rights: '(c) 2026 ACME'))
    ->encode(new JpegOutput(quality: 82));

(new DctReader())->read($images->openBytes($jpeg->bytes)->toImage(), $key);   // status Authentic + payload
(new MetadataWatermarkReader())->read($jpeg, $key);
```

- **Visible:** any image composited with an opacity and a blend mode. The separable W3C blend modes
  (Normal, Multiply, Screen, Overlay, Darken, Lighten, Difference, HardLight, SoftLight, ColorDodge,
  ColorBurn, Exclusion) are available on both drivers; the non-separable modes (Hue, Saturation, Color,
  Luminosity) are not implemented yet.
- **Steganographic:**
  - `LsbWatermark` holds up to 4 KB. It is fragile: lossless outputs only (PNG, lossless WebP).
  - `DctWatermark` holds 24 bytes and survives JPEG down to about quality 40.
  - Both are always applied as the last pixel step.
- **Metadata:** XMP (with an optional signature), EXIF Artist/Copyright and IPTC. They are written byte-level
  without touching the image data. This is a claim of origin, not proof of pixel integrity.
- **Readings** report one status:
  - `Absent`: no watermark was found.
  - `Intact`: CRC OK, no key.
  - `Authentic`: the HMAC matched the key.
  - `Tampered`: the CRC or HMAC does not match.
  - `Unverified`: signed, but no key was given.

## Drivers

`ext-gd` and `ext-imagick` are both optional, and either one alone runs the library: the API, the planner and
every pure-PHP part (GIF container, metadata, steganography, analysis) are the same on both; what differs is
listed in the table below. Install both and the planner uses them together. For every step it compares what each
installed driver offers - `Native`, `PhpFallback`, `Degraded` or `None` - against the cost of moving pixels
between drivers, and picks the cheapest route. Nothing needs configuring: a missing extension is simply not a
candidate.

### GD vs. Imagick

Each row is what the driver reports to the planner. Imagick probes its ImageMagick build once per process
wherever builds are known to differ, so the same code answers differently on ImageMagick 6 and 7.

| | GD | Imagick |
|---|---|---|
| JPEG, PNG, GIF, WebP (still images) | Native, per libgd build (`imagetypes()`) | Native, per ImageMagick coder |
| AVIF | Native when libgd is built with libavif | Native on ImageMagick 7; Degraded on ImageMagick 6 (Debian bookworm: wrong colors on decode, alpha dropped on encode) |
| HEIC/HEIF input | None | Native when the HEIC coder is present; decode only, no driver writes HEIC |
| Animated GIF | Native (pure-PHP container, frames decoded by the driver) | Native |
| Animated WebP | None | Native on ImageMagick 7; None on ImageMagick 6 |
| Embedded ICC profile to sRGB | Degraded: the profile is ignored, so wide-gamut photos look muted and CMYK is wrong | Native when ImageMagick is built with lcms, Degraded otherwise |
| Resize filters | `Nearest` or one resampled path (`Bilinear`, `Bicubic`, `Lanczos` render identically) | 1:1 `Imagick::FILTER_*` |
| Drawing | Degraded: only hairlines are anti-aliased, thick strokes and filled shapes have jagged edges | Native anti-aliasing |
| `blur()` of transparent pixels | Degraded: only the colour channels are blurred, alpha stays sharp, so a blurred drop shadow keeps hard edges; blur an opaque layer and blend it with `BlendMode::Multiply` instead, as `examples/bar-chart.php` does | Native |
| Text outside the Basic Multilingual Plane (emoji) | placeholder glyph | drawn |
| Blend modes | `Normal` native; the other separable modes run the PHP compositor over the overlay's area | Native operators, each probed once against the W3C formula; modes ImageMagick computes differently fall back to the PHP compositor |
| `trim()` | PhpFallback (per-pixel scan) | Native |
| Pixel buffer steps: `applyPixels()`, steganographic watermarks, blend fallback | PhpFallback: no bulk export, reads are an `imagecolorat()` loop (about 1.1 s per 12 MP), writes go through a stored PNG | Native: raw RGBA blob both ways |
| Alpha channel precision | 7-bit (128 levels): the lowest bit of an 8-bit alpha is lost | full 8-bit |
| Geometry, color adjustments, filters, composite, pad | Native | Native |
| `autoOrient()`, metadata watermarks and stripping, analysis | pure PHP, identical | pure PHP, identical |

GD's 7-bit alpha is also why both steganographic watermarks embed only in R, G and B of opaque pixels on every
driver: an alpha LSB would not survive a GD round trip, so it is never used, and capacity is the same on both.

With both drivers installed the practical outcome is: Imagick decodes anything carrying a wide-gamut or CMYK
profile, HEIC and animated WebP, and renders drawing, `trim()` and buffer steps; GD keeps the steps it does
natively (ties stay on the driver already holding the image, then registry order, GD first). A hand-off is a
lossless PNG that costs about as much as one PHP pass, so the planner moves an image for a `Degraded` or `None`
step, or when several fallback steps cluster, not to save a single one.

Force a driver, for example for debugging, with `Configuration::default()->withForcedDriver(DriverName::Gd)`.

The prices the planner compares are relative nanoseconds per pixel, fitted to a CPU-only build of GD and
ImageMagick 7. A machine whose drivers perform differently, say a GPU-accelerated ImageMagick where native
steps and the PNG hand-off between drivers are far cheaper than a PHP pass, can hand in its own measurements:

```php
use Domm98CZ\Image\Pipeline\CostModel;

$costs = new CostModel(nativeNanosPerPixel: 1.0, phpFallbackNanosPerPixel: 150.0, transferNanosPerPixel: 20.0);
$images = new ImageFactory(Configuration::default()->withCostModel($costs));
```

Only the ratios matter; `degradedNanosPerPixel` stays far above the rest so a lossless path still wins whenever
one exists. The constructor enforces that much: every cost must be a non-negative number (zero is allowed) and
`degradedNanosPerPixel` must exceed the other three, otherwise it throws `InvalidConfigurationException`.

## Security

- **Input size is capped while reading.** Nothing trusts `getSize()` or `filesize()`.
- **Formats are recognised by magic bytes**, never by the file extension.
- **Pure-PHP header probes read the declared dimensions and frame count.** These are checked against `Limits`
  and against the remaining `memory_limit` before any decode. Imagick's resource limits are set from the same
  values. An `Image` or frames built elsewhere (another factory, the static `ImageBuilder::from()` shortcut)
  are checked against the receiving factory's `Limits` at `from()` and `animation()`, before any work.
  The `memory_limit` check (`checkMemoryLimit`) covers decoded input only: `create()`, results of upscaling
  operations and images taken over through `from()` are bounded by `maxWidth`/`maxHeight`/`maxPixels` alone
  (50 MP is about 200 MB on GD and about 400 MB on Imagick Q16).
- **Every length field in a byte-level parser is bounds-checked.** This covers the GIF container, JPEG
  segments, PNG/WebP chunks and AVIF boxes. Compressed PNG profiles are inflated with a cap.
- **XMP parsing refuses DTDs and entities** (XXE, billion laughs) and never touches the network.
- **External tools run via an argument array** (no shell), with a timeout and an output cap.

| `Limits` default | |
|---|---|
| `maxWidth` / `maxHeight` | 16 384 px |
| `maxPixels` | 50 MP |
| `maxFrames` | 500 |
| `maxAnimationPixels` | 200 MP (all frames together) |
| `maxInputBytes` | 50 MB |
| `checkMemoryLimit` | on |

## Exceptions

Everything the library throws implements `Domm98CZ\Image\Exception\ImageException`. The abstract categories
are:

- `InvalidArgumentException`: bad values from your code.
- `InvalidInputException`: an unrecognized or corrupted image, or an exceeded limit.
- `UnsupportedFeatureException`: no installed driver can do it.
- `DriverException`: an extension failed or is missing.
- `InputOutputException`: reading or writing failed.
- `ProcessingException`: for example, an external tool failed.

```php
try {
    $images->open($upload)->fitInside(new Dimensions(1600, 1600))->save('out.webp');
} catch (\Domm98CZ\Image\Exception\InvalidInputException $exception) {
    // hostile or corrupted upload, or a Limits ceiling - safe to reject with a 4xx
} catch (\Domm98CZ\Image\Exception\ImageException $exception) {
    // anything else the library throws
}
```

## Contributing / running tests

Everything runs in Docker (Debian trixie with PHP 8.4 and ImageMagick 7):

```sh
docker compose build
HOST_UID=$(id -u) HOST_GID=$(id -g) docker compose run --rm php composer install
HOST_UID=$(id -u) HOST_GID=$(id -g) docker compose run --rm php composer check     # cs + phpstan + unit + integration
```

| Script | Runs |
|---|---|
| `composer test` | unit suite (fake driver, no extensions needed) |
| `composer test:integration` | integration suite (real ext-gd / ext-imagick) |
| `composer phpstan` | PHPStan, level max + strict rules, `src/` only |
| `composer cs` / `composer cs:fix` | PHP-CS-Fixer, PER-CS 2.0 |

The library supports PHP 8.2 through 8.5; the development image pins PHP 8.4.
