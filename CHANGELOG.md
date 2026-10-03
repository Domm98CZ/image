# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project uses
[Semantic Versioning](https://semver.org/) once tagged.

## [Unreleased]

Initial public release candidate. Nothing has shipped yet.

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
- Line chart and bar chart examples (`examples/line-chart.php`, `examples/bar-chart.php`) showing supersampled
  drawing, rotated text, translucent fills and a blended drop-shadow layer.

### Fixed

- GD: a translucent polygon filled after a thick stroke was blended several times over (after a 3 px stroke a
  16% fill looked like 41%), because the stroke thickness stayed set; drawing now restores it after every stroke.

[Unreleased]: https://github.com/Domm98CZ/image/commits/main
