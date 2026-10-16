# zeimoto Image

`zeimoto/image` provides three responsive, art-directed image helpers. They
share source resolution, image processing and trusted `<picture>` markup.
Derivatives default to WebP at quality 80 without modifying the stored source. The
kit's default file blueprint also converts uploads to WebP.

## Installation

Requires PHP 8.5 and Kirby 5.6.1 or later within Kirby 5.
The Composer package is `zeimoto/image` and installs into
`site/plugins/zeimoto-image`. Release versions come from Git tags.

Once published on Packagist:

```sh
composer require zeimoto/image
```

For manual installation, copy this plugin into `site/plugins/zeimoto-image`.
See the integration requirements below before using its public surfaces.
The plugin is distributed under the MIT license; see [LICENSE](LICENSE).

The helpers supply their Kirby context explicitly and can be called outside
page rendering. They require no frontend JavaScript or Panel build.

## Shared options

All helpers accept:

- `image`: shared source for both viewport orientations, as a Kirby image
  Field, File or null.
- `landscape`, `portrait`: optional orientation-specific sources of the same
  types. Landscape resolves `landscape` → `image` → `portrait`; portrait resolves
  `portrait` → `image` → `landscape`. One source is enough. No resolved source
  means empty markup when the helper's required options are valid.
- `class`: defaults to `image`.
- `alt`: defaults to the landscape source's saved alt text, or the portrait
  source's when landscape is missing. Pass an empty string for decorative images.
- `loading`: `lazy` (default) or `eager`.
- `fetchpriority`: `auto` (default), `high` or `low`.
- `sizes`: optional nonempty HTML source-size string. Defaults to `auto, 100vw`
  for lazy loading and `100vw` for eager loading. An auto-prefixed value is
  rejected for eager images.

## Single-image usage

Gallery items, individual image blocks and other single-image consumers can use:

```php
echo zeimotoImage(["image" => $image]);
echo zeimotoImageCropped(["image" => $image]);
echo zeimotoImageThumbnail([
  "image" => $image,
  "dimensions" => [800, 600],
]);
```

Add `landscape` or `portrait` to override the shared source for that viewport
orientation. Each selected file supplies its own focal point and, for the
cropped helper, its own saved ratio/crop settings. Existing orientation-only
calls continue to work. Without an explicit alt override, alt text comes from
the resolved landscape source, including the shared image when it supplies it.

## Project configuration

All helpers share configurable output format and quality. Full-image and
saved-crop helpers also share breakpoint maximums:

```php
"zeimoto.image.breakpoints" => [
  "mobile" => 0,
  "tablet" => 640,
  "laptop" => 1024,
  "desktop" => 1536,
],
"zeimoto.image.format" => "webp",
"zeimoto.image.quality" => 80,
"zeimoto.image.widths" => [320, 480, 640, 960, 1280, 1600, 1920, 2240, 2560],
"zeimoto.image.maxDimensionsDesktop" => [2560, 2560],
"zeimoto.image.maxDimensionsLaptop" => [2240, 2240],
"zeimoto.image.maxDimensionsTablet" => [1920, 1920],
"zeimoto.image.maxDimensionsMobile" => [1280, 1280],
```

The kit keeps project values in `site/config/zeimoto-image.php`, explicitly included
by `site/config/config.php`. Edit this file without changing the plugin. In
other projects, add these keys directly to Kirby's configuration or include
the same file. Kirby does not automatically load arbitrary config filenames.
Host-specific configuration can override the keys too. The plugin declares
safe defaults so it works when no project configuration is supplied.

Breakpoint values are minimum viewport widths: mobile must start at 0, with
positive integer tablet, laptop, and desktop starts in ascending order. The kit's
MDS uses matching pixel thresholds with exclusive upper bounds. SCSS and PHP
remain separate definitions: project breakpoint changes must update both.
Changing PHP configuration does not automatically change SCSS. Ranges remain continuous.

Explicit view dimensions take precedence over project configuration. Thumbnail
helpers retain their required view-defined base dimensions and do not use these
maximums, but they use the shared format and quality. Quality must be an integer
from 1 to 100; invalid values throw `InvalidArgumentException`. Format support depends on Kirby's configured image-processing driver.
These settings affect generated derivatives, not upload conversion configured
by the file blueprint.

## Full composition: `zeimotoImage()`

```php
echo zeimotoImage([
  "landscape" => $landscape,
  "portrait" => $portrait,
  "maxDimensionsDesktop" => [2560, 2560],
  "maxDimensionsLaptop" => [2240, 2240],
  "maxDimensionsTablet" => [1920, 1920],
  "maxDimensionsMobile" => [1280, 1280],
]);
```

Ignores saved ratio/crop settings and preserves the entire composition. Generates
eight breakpoint/orientation sources using square maximum bounds of 2560px
(desktop), 2240px (laptop), 1920px (tablet), and 1280px (mobile) by default. Override them with `maxDimensionsDesktop`, `maxDimensionsLaptop`, `maxDimensionsTablet`,
and `maxDimensionsMobile` positive-integer dimension pairs. Smaller images are never
upscaled. Limits are used as supplied for either orientation.

Optional positive-integer `maxWidth` and `maxHeight` apply additional caps to
every breakpoint. Missing or null caps retain the breakpoint limits. Other
helpers do not use these options. Values other than positive integers or null
throw `InvalidArgumentException`.

## Saved crop: `zeimotoImageCropped()`

```php
echo zeimotoImageCropped([
  "landscape" => $landscape,
  "portrait" => $portrait,
  "class" => "mds-hero-image",
  "loading" => "eager",
  "fetchpriority" => "high",
]);
```

Each selected file's saved crop toggle and ratio determine its composition.
Crop on with a valid ratio crops around its focal point, or the center.
Crop off or an empty/invalid ratio preserves the entire image proportionally.
Portrait choices include `9:16`, `3:4`, and `2:3`; the selected ratio is never
inverted for portrait viewports.

Maximum dimensions default to:

```php
"maxDimensionsDesktop" => [2560, 2560],
"maxDimensionsLaptop" => [2240, 2240],
"maxDimensionsTablet" => [1920, 1920],
"maxDimensionsMobile" => [1280, 1280],
```

Limits are used in the supplied width/height order for either orientation.
The source's saved ratio determines the output shape. No upscaling.

## View-defined crop: `zeimotoImageThumbnail()`

```php
echo zeimotoImageThumbnail([
  "landscape" => $landscape,
  "portrait" => $portrait,
  "dimensions" => [1200, 800],
  "dimensionsDesktop" => [1920, 1080],
  "dimensionsLaptop" => [1600, 1000],
  "dimensionsTablet" => [1280, 960],
  "dimensionsMobile" => [720, 960],
]);
```

`dimensions` is required even when all overrides are supplied. Optional
`dimensionsDesktop`, `dimensionsLaptop`, `dimensionsTablet`, and `dimensionsMobile` use the base
when missing or null. Dimensions define the target ratio and maximum resolution;
smaller originals yield smaller crops without upscaling. Dimensions are never
swapped for portrait viewports. Saved ratio/crop settings are ignored; the
stored source's focal point is used, falling back to the center.

Dimension pairs must contain exactly two positive integers. Malformed pairs,
or missing/null base thumbnail dimensions, throw `InvalidArgumentException`.
The saved-crop helper validates its dimension pairs in the same way.

## Responsive sources

All three helpers emit eight sources using continuous viewport-width ranges
(by default): below 640px (mobile), 640px to below 1024px (tablet), 1024px to below
1536px (laptop), and 1536px upward (desktop), each with landscape and portrait orientations. Every source has its
own generated width and height so the browser can reserve its proportions.

All helpers support these source-selection defaults; each option accepts
`landscape` or `portrait`:

```php
"desktopLandscape" => "landscape",
"desktopPortrait" => "portrait",
"laptopLandscape" => "landscape",
"laptopPortrait" => "portrait",
"tabletLandscape" => "landscape",
"tabletPortrait" => "portrait",
"mobileLandscape" => "landscape",
"mobilePortrait" => "portrait",
```

## Migrating from `zeimotoImagePicture()`

The old helper is removed without an alias. Replace full-image calls with
`zeimotoImage()`. Replace calls using `cropped: true` with
`zeimotoImageCropped()` and remove that flag. Replace inline-thumbnail calls
with `zeimotoImageThumbnail()` and supply the required base `dimensions` pair.
Partial thumbnail overrides now use this base instead of another rendering mode.
Each helper selects its own mode; caller options cannot change it. Options for
other helpers have no effect.

## Migrating three-range configurations

Update existing breakpoint configuration to include `laptop` and all four
ascending starts shown above. A resolved configuration missing `laptop` or
containing invalid starts throws `InvalidArgumentException`. Update matching
SCSS thresholds too; existing MDS mixin names and cumulative behavior remain.

Existing helper calls remain valid. Full and saved-crop calls use the configured
laptop maximum unless `maxDimensionsLaptop` is supplied. Thumbnail calls use
base `dimensions` for laptop unless `dimensionsLaptop` is supplied; laptop does
not inherit the desktop override. Laptop source selection defaults to the matching
orientation, with optional `laptopLandscape` and `laptopPortrait` overrides.

## Resolution selection

Each orientation/breakpoint source and the fallback image offer width-based
`srcset` candidates. `zeimoto.image.widths` must be a nonempty, strictly ascending
list of positive integer widths. Candidate widths stop at the resolved output
ceiling, and that ceiling is always included even when absent from the list.
Actual generated widths supply the descriptors; duplicate widths are removed.
Candidates retain the selected composition, crop ratio, and focal point, with
pixel rounding at smaller resolutions. Stored originals are never enlarged.

Lazy images default to `sizes="auto, 100vw"`. Supporting browsers use their
actual displayed width and screen density when choosing a candidate. Other
browsers use the conservative `100vw` fallback and may download larger files
for gallery columns. CSS block widths are not duplicated in PHP.

All helpers accept an explicit `sizes` string, for example:

```php
echo zeimotoImage([
  "image" => $image,
  "loading" => "eager",
  "sizes" => "(width < 640px) 100vw, 50vw",
]);
```

Eager images default to `100vw`; `auto` requires lazy loading and is rejected
for eager calls. Keep width/height attributes and CSS sizing to reserve layout
space. The helper validates type, emptiness, and eager-auto usage, not the full
HTML sizes grammar. Source media queries still select composition; sizes selects
resolution within it. The fallback `src` remains the desktop-landscape maximum.

## Development environment

Open `kirby.code-workspace` while this plugin is installed under the zeimoto Kirby
Kit's `site/plugins/` directory. The workspace uses the kit's Composer and npm
dependencies, shared formatting rules, and Kirby classes for autocomplete. IDE
stubs are included; local editor settings, caches, private notes, and `.env` are
ignored. The formatter launcher currently requires Herd's PHP 8.5 on macOS.

Install Composer and npm dependencies at the kit root. From that root, check this
plugin with:

```sh
node tools/check-style.mjs --plugin site/plugins/zeimoto-image
```

## Behavior tests

Run `composer install` and `composer test` from this repository. Tests boot Kirby
with disposable runtime directories and never load project secrets or send real
mail. The host kit can also run this suite with `composer test:plugins`.
