# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project uses
[Semantic Versioning](https://semver.org/) once tagged.

## [Unreleased]

## [0.2.1] - 2026-10-20

### Added

- README notes on the memory estimate, the stripper's whitelist, that a truncated JPEG is decoded rather than
  refused, that exception messages may contain paths and native messages (log them, do not show them), and that
  `save()` with an explicit output accepts an extension it does not know (`save('shell.php', new JpegOutput())`).

### Changed

- The memory check at `open()` now estimates 4 bytes per pixel for every frame plus two full copies (was one).
  The old estimate was below what GD actually needs to write an opaque PNG (about 11.5 bytes per pixel), so a
  25 MP PNG under `memory_limit=256M` ended in a fatal "Allowed memory size exhausted" instead of a
  `LimitExceededException`. Inputs close to the remaining `memory_limit` that opened before can now be refused.
- `MetadataStripper` keeps a whitelist instead of dropping a list of known metadata: unknown JPEG segments, PNG
  chunks and WebP chunks (also inside animation frames) are removed. A truncated JPEG or PNG, or a PNG with an
  unknown critical chunk, now throws `CorruptedImageException`.

### Fixed

- `MetadataStripper` let through data after a JPEG's end-of-image marker, comments and EXIF between the scans
  of a progressive JPEG, the JFIF APP0 thumbnail and JFXX extension thumbnails, unknown WebP chunks and the
  free-text name of a PNG ICC profile (now replaced by `ICC Profile`).
- GD: JPEG output no longer carries the `CREATOR: gd-jpeg` comment libgd adds to every file.

## [0.2.0] - 2026-10-19

### Added

- Line chart and bar chart examples (`examples/line-chart.php`, `examples/bar-chart.php`) showing supersampled
  drawing, rotated text, translucent fills and a blended drop-shadow layer.
- Expected output files for every script in `examples/` (`examples/expected/`).

### Fixed

- GD: a translucent polygon filled after a thick stroke was blended several times over (after a 3 px stroke a
  16% fill looked like 41%), because the stroke thickness stayed set; drawing now restores it after every stroke.

## [0.1.0] - 2026-09-30

Initial public release.

### Added

- Driver-agnostic image manipulation over GD and/or Imagick (either extension alone runs the full
  API; with both installed, the planner picks the cheaper driver per step).
- Geometry: resize, crop, fit and thumbnail, rotate, flip, EXIF auto-orientation.
- Color and pixels: filters, color adjustments, pixel access, drawing and text, alpha everywhere.
- Animation: animated GIF (any driver) and animated WebP (Imagick).
- Formats: JPEG, PNG, GIF, WebP and AVIF with typed encoder options, plus HEIC/HEIF decode-only
  input (Imagick).
- Watermarks: visible, steganographic (LSB / DCT) and metadata (XMP / EXIF / IPTC), each optionally
  keyed and signed.
- Color management: embedded ICC profiles converted to sRGB.
- Analysis: `blurhash()`, `perceptualHash()`, `histogram()`, `dominantColor()` / `palette()`.
- Optimization: `MetadataStripper` plus external adapters for `pngquant`, `jpegtran`, `cwebp` and
  `gifsicle`, and `encodeSmallest()` for picking the smallest of several encodings.
- PSR-7 / PSR-17 interop for input streams and output responses.
- Security-by-default input handling: byte caps, magic-byte format sniffing and a
  decompression-bomb guard ahead of any decode.

[Unreleased]: https://github.com/Domm98CZ/image/compare/0.2.1...HEAD
[0.2.1]: https://github.com/Domm98CZ/image/compare/0.2.0...0.2.1
[0.2.0]: https://github.com/Domm98CZ/image/compare/0.1.0...0.2.0
[0.1.0]: https://github.com/Domm98CZ/image/releases/tag/0.1.0
