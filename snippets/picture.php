<?php
// ==========================================================
// Plugins :: zeimoto :: Image :: Snippets :: Picture
// ==========================================================
//
// Renders the plugin's responsive, art-directed picture
// markup. The public integration contract lives in README.md.
//
// ==========================================================

declare(strict_types=1);

use Kirby\Cms\File;
use Kirby\Cms\FileVersion;
use Kirby\Content\Field;

$imageMode ??= "full";
$validateDimensions = static function (mixed $dimensions, string $name): array {
  if (!is_array($dimensions) || !array_is_list($dimensions) || count($dimensions) !== 2 ||
    !is_int($dimensions[0]) || !is_int($dimensions[1]) || $dimensions[0] <= 0 || $dimensions[1] <= 0
  ) {
    throw new InvalidArgumentException("{$name} must be [width, height] containing two positive integers.");
  }

  return $dimensions;
};

if ($imageMode === "thumbnail") {
  $dimensions = $validateDimensions($dimensions ?? null, "dimensions");
  $desktopDimensions = $validateDimensions($dimensionsDesktop ?? $dimensions, "dimensionsDesktop");
  $laptopDimensions = $validateDimensions($dimensionsLaptop ?? $dimensions, "dimensionsLaptop");
  $tabletDimensions = $validateDimensions($dimensionsTablet ?? $dimensions, "dimensionsTablet");
  $mobileDimensions = $validateDimensions($dimensionsMobile ?? $dimensions, "dimensionsMobile");
} else {
  $desktopDefault = $kirby->option("zeimoto.image.maxDimensionsDesktop");
  $laptopDefault = $kirby->option("zeimoto.image.maxDimensionsLaptop");
  $tabletDefault = $kirby->option("zeimoto.image.maxDimensionsTablet");
  $mobileDefault = $kirby->option("zeimoto.image.maxDimensionsMobile");

  $desktopDimensions = $validateDimensions($maxDimensionsDesktop ?? $desktopDefault, "maxDimensionsDesktop");
  $laptopDimensions = $validateDimensions($maxDimensionsLaptop ?? $laptopDefault, "maxDimensionsLaptop");
  $tabletDimensions = $validateDimensions($maxDimensionsTablet ?? $tabletDefault, "maxDimensionsTablet");
  $mobileDimensions = $validateDimensions($maxDimensionsMobile ?? $mobileDefault, "maxDimensionsMobile");
}

$toFile = static function (mixed $image): ?File {
  if ($image instanceof File) {
    return $image;
  }

  return $image instanceof Field ? $image->toFile() : null;
};
$image = $toFile($image ?? null);
$landscape = $toFile($landscape ?? null);
$portrait = $toFile($portrait ?? null);

$landscapeSource = $landscape ?? $image ?? $portrait;
$portraitSource = $portrait ?? $image ?? $landscape;

if ($landscapeSource === null) {
  return;
}

$format = $kirby->option("zeimoto.image.format");
$quality = $kirby->option("zeimoto.image.quality");

if (!is_int($quality) || $quality < 1 || $quality > 100) {
  throw new InvalidArgumentException("zeimoto.image.quality must be an integer from 1 to 100.");
}

$maxWidth ??= null;
$maxHeight ??= null;

if ($imageMode === "full") {
  foreach (["maxWidth" => $maxWidth, "maxHeight" => $maxHeight] as $name => $limit) {
    if ($limit !== null && (!is_int($limit) || $limit <= 0)) {
      throw new InvalidArgumentException("{$name} must be a positive integer or null.");
    }
  }
}

$desktopLandscape ??= "landscape";
$desktopPortrait ??= "portrait";
$laptopLandscape ??= "landscape";
$laptopPortrait ??= "portrait";
$tabletLandscape ??= "landscape";
$tabletPortrait ??= "portrait";
$mobileLandscape ??= "landscape";
$mobilePortrait ??= "portrait";
$class = is_string($class ?? null) ? $class : "image";
$alt = is_string($alt ?? null) ? $alt : $landscapeSource->alt()->value();
$loading = ($loading ?? null) === "eager" ? "eager" : "lazy";
$sizes ??= $loading === "lazy" ? "auto, 100vw" : "100vw";
if (!is_string($sizes) || trim($sizes) === "") {
  throw new InvalidArgumentException("sizes must be a nonempty source-size string.");
}
$sizes = trim($sizes);
if ($loading === "eager" && preg_match('/^auto(?:\s*,|$)/i', $sizes) === 1) {
  throw new InvalidArgumentException("sizes cannot start with auto when loading is eager.");
}
$widths = $kirby->option("zeimoto.image.widths");
if (!is_array($widths) || !array_is_list($widths) || $widths === []) {
  throw new InvalidArgumentException("zeimoto.image.widths must be a nonempty, strictly ascending list of positive integers.");
}
$previousWidth = 0;
foreach ($widths as $width) {
  if (!is_int($width) || $width <= $previousWidth) {
    throw new InvalidArgumentException("zeimoto.image.widths must be a nonempty, strictly ascending list of positive integers.");
  }
  $previousWidth = $width;
}
$fetchpriority = match ($fetchpriority ?? null) {
  "high", "low" => $fetchpriority,
  default => "auto",
};

$normalizeOrientation = static fn(string $orientation): string => $orientation === "portrait" ? "portrait" : "landscape";
$source = static function (string $orientation) use ($landscapeSource, $portraitSource): File {
  $orientation = $orientation === "portrait" ? "portrait" : "landscape";

  return $orientation === "portrait" ? $portraitSource : $landscapeSource;
};
$processedImage = static function (string $orientation, array $dimensions, bool $useExplicitDimensions = false) use ($normalizeOrientation, $source, $format, $quality): mixed {
  [$width, $height] = $dimensions;
  $orientation = $normalizeOrientation($orientation);

  $image = $source($orientation);
  $ratio = $image->content()->get("ratio")->value();
  $crop = false;

  if ($useExplicitDimensions) {
    $scale = min(1, $image->width() / $width, $image->height() / $height);
    $width = max(1, (int) round($width * $scale));
    $height = max(1, (int) round($height * $scale));
    $crop = $image->focus()->value() ?? "center";
  } elseif ($image->content()->get("crop")->isTrue() && preg_match('~^([1-9][0-9]*)/([1-9][0-9]*)$~', $ratio, $parts) === 1) {
    $ratioWidth = (int) $parts[1];
    $ratioHeight = (int) $parts[2];
    $scale = min(
      $width / $ratioWidth,
      $height / $ratioHeight,
      $image->width() / $ratioWidth,
      $image->height() / $ratioHeight,
    );
    $width = max(1, (int) round($ratioWidth * $scale));
    $height = max(1, (int) round($ratioHeight * $scale));
    $crop = $image->focus()->value() ?? "center";
  }

  return $image->thumb([
    "width" => $width,
    "height" => $height,
    "crop" => $crop,
    "format" => $format,
    "quality" => $quality,
  ]);
};

$fullImage = static fn(File $image, array $dimensions): mixed => $image->thumb([
  "width" => min($dimensions[0], $maxWidth ?? $dimensions[0]),
  "height" => min($dimensions[1], $maxHeight ?? $dimensions[1]),
  "crop" => false,
  "format" => $format,
  "quality" => $quality,
]);

$breakpoints = $kirby->option("zeimoto.image.breakpoints");
if (!is_array($breakpoints) || ($breakpoints["mobile"] ?? null) !== 0 ||
  !is_int($breakpoints["tablet"] ?? null) || !is_int($breakpoints["laptop"] ?? null) || !is_int($breakpoints["desktop"] ?? null) ||
  $breakpoints["tablet"] <= 0 || $breakpoints["laptop"] <= $breakpoints["tablet"] || $breakpoints["desktop"] <= $breakpoints["laptop"]
) {
  throw new InvalidArgumentException("zeimoto.image.breakpoints must define mobile: 0 and positive integer tablet < laptop < desktop starts.");
}
$mobileStart = $breakpoints["mobile"];
$tabletStart = $breakpoints["tablet"];
$laptopStart = $breakpoints["laptop"];
$desktopStart = $breakpoints["desktop"];

$breakpointImage = static fn(string $orientation, array $dimensions): mixed => $imageMode === "full"
  ? $fullImage($source($orientation), $dimensions)
  : $processedImage($orientation, $dimensions, $imageMode === "thumbnail");
$sources = [
  ["media" => "(width >= {$desktopStart}px) and (orientation: landscape)", "image" => $breakpointImage($desktopLandscape, $desktopDimensions)],
  ["media" => "(width >= {$desktopStart}px) and (orientation: portrait)", "image" => $breakpointImage($desktopPortrait, $desktopDimensions)],
  ["media" => "({$laptopStart}px <= width < {$desktopStart}px) and (orientation: landscape)", "image" => $breakpointImage($laptopLandscape, $laptopDimensions)],
  ["media" => "({$laptopStart}px <= width < {$desktopStart}px) and (orientation: portrait)", "image" => $breakpointImage($laptopPortrait, $laptopDimensions)],
  ["media" => "({$tabletStart}px <= width < {$laptopStart}px) and (orientation: landscape)", "image" => $breakpointImage($tabletLandscape, $tabletDimensions)],
  ["media" => "({$tabletStart}px <= width < {$laptopStart}px) and (orientation: portrait)", "image" => $breakpointImage($tabletPortrait, $tabletDimensions)],
  ["media" => "({$mobileStart}px <= width < {$tabletStart}px) and (orientation: landscape)", "image" => $breakpointImage($mobileLandscape, $mobileDimensions)],
  ["media" => "({$mobileStart}px <= width < {$tabletStart}px) and (orientation: portrait)", "image" => $breakpointImage($mobilePortrait, $mobileDimensions)],
];
$candidateSets = [];
foreach ($sources as &$entry) {
  $maximum = $entry["image"];
  $key = $maximum->url();
  if (!isset($candidateSets[$key])) {
    $candidates = [];
    if ($maximum instanceof FileVersion) {
      foreach ($widths as $width) {
        if ($width >= $maximum->width()) {
          break;
        }
        $candidate = $maximum->original()->thumb([
          ...$maximum->modifications(),
          "width" => $width,
          "height" => max(1, (int) round($maximum->height() * $width / $maximum->width())),
        ]);
        $candidates[$candidate->width()] = $candidate->url();
      }
    }
    $candidates[$maximum->width()] = $maximum->url();
    ksort($candidates, SORT_NUMERIC);
    $candidateSets[$key] = implode(", ", array_map(
      static fn(int $width, string $url): string => "{$url} {$width}w",
      array_keys($candidates),
      array_values($candidates),
    ));
  }
  $entry["srcset"] = $candidateSets[$key];
}
unset($entry);
$fallback = $sources[0]["image"];
?>

<picture>
  <?php foreach ($sources as $source): ?>
    <source
      media="<?= html($source["media"]) ?>"
      srcset="<?= html($source["srcset"]) ?>"
      sizes="<?= html($sizes) ?>"
      width="<?= html((string) $source["image"]->width()) ?>"
      height="<?= html((string) $source["image"]->height()) ?>"
    />
  <?php endforeach; ?>
  <img
    class="<?= html($class) ?>"
    src="<?= html($fallback->url()) ?>"
    srcset="<?= html($sources[0]["srcset"]) ?>"
    sizes="<?= html($sizes) ?>"
    alt="<?= html($alt) ?>"
    width="<?= html((string) $fallback->width()) ?>"
    height="<?= html((string) $fallback->height()) ?>"
    loading="<?= html($loading) ?>"
    fetchpriority="<?= html($fetchpriority) ?>"
  />
</picture>
