<?php
// ==========================================================
// Tests :: Image Contracts
// ==========================================================
//
// Exercises the plugin public behavior with disposable Kirby fixtures.
//
// ==========================================================

declare(strict_types=1);

namespace Zeimoto\Image\Tests;

use Kirby\Cms\Page;
use PHPUnit\Framework\Attributes\DataProvider;

final class ContractTest extends PluginTestCase
{
  public function testMissingSourcesReturnEmptyMarkup(): void
  {
    $this->app();
    self::assertSame("", zeimotoImage([]));
    self::assertSame("", zeimotoImageCropped([]));
    self::assertSame("", zeimotoImageThumbnail(["dimensions" => [200, 100]]));
  }

  public static function invalidDimensions(): array
  {
    return [[[0, 100]], [[100]], [["100", 100]], [[100, -1]]];
  }

  #[DataProvider("invalidDimensions")]
  public function testInvalidDimensionsAreRejected(array $dimensions): void
  {
    $this->app();
    $this->expectException(\InvalidArgumentException::class);
    zeimotoImageThumbnail(["dimensions" => $dimensions]);
  }

  public function testResponsiveMarkupAndAltEscaping(): void
  {
    $this->app();
    $path = $this->directory . "/content/images";
    mkdir($path);
    $image = imagecreatetruecolor(640, 320);
    imagepng($image, $path . "/photo.png");
    $page = new Page(["slug" => "images", "files" => [["filename" => "photo.png", "content" => ["alt" => "A & \"B\""]]]]);
    $html = zeimotoImage(["image" => $page->file("photo.png")]);
    self::assertStringContainsString("<picture", $html);
    self::assertStringContainsString("srcset=", $html);
    self::assertStringContainsString("A &amp; &quot;B&quot;", $html);
    self::assertStringContainsString("loading=\"lazy\"", $html);
    self::assertStringContainsString("width=", $html);
    self::assertStringContainsString("height=", $html);
  }
  public function testFileRatioAndCropProduceSquareOutputWithoutChangingSource(): void
  {
    $this->app();
    $path = $this->directory . "/content/cropped";
    mkdir($path);
    $image = imagecreatetruecolor(640, 320);
    imagepng($image, $path . "/photo.png");
    $hash = hash_file("sha256", $path . "/photo.png");
    $page = new Page(["slug" => "cropped", "files" => [["filename" => "photo.png", "content" => ["ratio" => "1/1", "crop" => "true"]]]]);
    $html = zeimotoImageCropped(["image" => $page->file("photo.png")]);
    $dom = new \DOMDocument();
    @$dom->loadHTML($html);
    $img = $dom->getElementsByTagName("img")->item(0);
    self::assertNotNull($img);
    self::assertSame($img->getAttribute("width"), $img->getAttribute("height"));
    self::assertSame($hash, hash_file("sha256", $path . "/photo.png"));
  }

}
