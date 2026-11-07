<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\ImageSanitizer;
use App\Tests\TestImage;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

#[RequiresPhpExtension('gd')]
#[RequiresPhpExtension('exif')]
final class ImageSanitizerTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/photoprix_' . uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        rmdir($this->dir);
    }

    public function testTheFixtureReallyContainsExif(): void
    {
        $path = TestImage::jpegWithExif($this->dir . '/a.jpg');

        $exif = exif_read_data($path);
        self::assertIsArray($exif);
        self::assertSame('Spy', $exif['Make']);
        self::assertSame(6, $exif['Orientation']);
        self::assertSame('N', $exif['GPSLatitudeRef']);
    }

    public function testJpegMetadataIsRemoved(): void
    {
        $path = TestImage::jpegWithExif($this->dir . '/a.jpg', orientation: 1);

        new ImageSanitizer()->sanitize($path);

        $exif = @exif_read_data($path);
        self::assertArrayNotHasKey('Make', $exif ?: []);
        self::assertArrayNotHasKey('GPSLatitudeRef', $exif ?: []);
        self::assertArrayNotHasKey('Orientation', $exif ?: []);
        self::assertSame('image/jpeg', getimagesize($path)['mime'] ?? null);
    }

    public function testOrientationIsAppliedBeforeStripping(): void
    {
        // Orientation 6, c'est une photo prise en portrait qu'il faut tourner de 90°.
        $path = TestImage::jpegWithExif($this->dir . '/portrait.jpg', 40, 20, 6);

        new ImageSanitizer()->sanitize($path);

        [$width, $height] = getimagesize($path) ?: [0, 0];
        self::assertSame([20, 40], [$width, $height]);
    }

    public function testPngTransparencyIsKept(): void
    {
        $path = TestImage::pngWithTransparency($this->dir . '/a.png');

        new ImageSanitizer()->sanitize($path);

        $image = imagecreatefrompng($path);
        self::assertNotFalse($image);
        $alpha = (imagecolorat($image, 0, 0) >> 24) & 0x7F;
        self::assertSame(127, $alpha);
    }

    public function testNonImageIsRejected(): void
    {
        $path = $this->dir . '/fake.jpg';
        file_put_contents($path, '<?php echo "pas une image";');

        $this->expectException(\RuntimeException::class);
        new ImageSanitizer()->sanitize($path);
    }
}
