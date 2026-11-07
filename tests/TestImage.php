<?php

declare(strict_types=1);

namespace App\Tests;

/**
 * Fabrique des images de test, dont des JPEG avec de vraies données EXIF (appareil, orientation, GPS).
 */
final class TestImage
{
    /**
     * Un JPEG en paysage avec des EXIF bien visibles : l'appareil « Spy », l'orientation demandée
     * et un morceau de GPS.
     */
    public static function jpegWithExif(string $path, int $width = 40, int $height = 20, int $orientation = 6): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 200, 50, 50));
        ob_start();
        imagejpeg($image);
        $jpeg = (string) ob_get_clean();

        // GD ne sait pas écrire d'EXIF, alors on glisse le bloc à la main juste après le début du JPEG.
        $exif = 'Exif' . "\0\0" . self::tiff($orientation);
        $app1 = "\xFF\xE1" . pack('n', strlen($exif) + 2) . $exif;
        file_put_contents($path, substr($jpeg, 0, 2) . $app1 . substr($jpeg, 2));

        return $path;
    }

    public static function pngWithTransparency(string $path): string
    {
        $image = imagecreatetruecolor(10, 10);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, (int) imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagepng($image, $path);

        return $path;
    }

    /**
     * Le strict minimum d'un bloc EXIF (format TIFF) : l'appareil, l'orientation et une info GPS.
     */
    private static function tiff(int $orientation): string
    {
        $entry = static fn (int $tag, int $type, int $count, string $value): string => pack('vvV', $tag, $type, $count) . str_pad($value, 4, "\0");

        $ifd0Offset = 8;
        $ifd0Entries = 3;
        $gpsOffset = $ifd0Offset + 2 + 12 * $ifd0Entries + 4;

        $ifd0 = pack('v', $ifd0Entries)
            . $entry(0x010F, 2, 4, "Spy\0")                    // Make (ASCII)
            . $entry(0x0112, 3, 1, pack('v', $orientation))     // Orientation (SHORT)
            . $entry(0x8825, 4, 1, pack('V', $gpsOffset))       // GPS IFD pointer (LONG)
            . pack('V', 0);

        $gps = pack('v', 1)
            . $entry(0x0001, 2, 2, "N\0")                       // GPSLatitudeRef
            . pack('V', 0);

        return 'II' . pack('v', 42) . pack('V', $ifd0Offset) . $ifd0 . $gps;
    }
}
