<?php
// ==========================================================
// Plugins :: zeimoto :: Image
// ==========================================================
//
// Responsive image helpers share the plugin's picture renderer.
//
// ==========================================================

declare(strict_types=1);

if (function_exists("zeimotoImage") === false) {
  function zeimotoImage(array $props): string
  {
    return snippet("image/picture", [...$props, "kirby" => kirby(), "imageMode" => "full"], true) ?? "";
  }
}

if (function_exists("zeimotoImageCropped") === false) {
  function zeimotoImageCropped(array $props): string
  {
    return snippet("image/picture", [...$props, "kirby" => kirby(), "imageMode" => "cropped"], true) ?? "";
  }
}

if (function_exists("zeimotoImageThumbnail") === false) {
  function zeimotoImageThumbnail(array $props): string
  {
    return snippet("image/picture", [...$props, "kirby" => kirby(), "imageMode" => "thumbnail"], true) ?? "";
  }
}

Kirby::plugin("zeimoto/image", [
  "options" => [
    "breakpoints" => [
      "mobile" => 0,
      "tablet" => 640,
      "laptop" => 1024,
      "desktop" => 1536,
    ],
    "format" => "webp",
    "quality" => 80,
    "widths" => [320, 480, 640, 960, 1280, 1600, 1920, 2240, 2560],
    "maxDimensionsDesktop" => [2560, 2560],
    "maxDimensionsLaptop" => [2240, 2240],
    "maxDimensionsTablet" => [1920, 1920],
    "maxDimensionsMobile" => [1280, 1280],
  ],
  "snippets" => [
    "image/picture" => __DIR__ . "/snippets/picture.php",
  ],
]);
