<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Ré-encode l'image avec GD pour n'en garder que les pixels : adieu EXIF, GPS et compagnie.
 * L'orientation est appliquée avant, sinon les photos prises en portrait arriveraient couchées.
 */
final class ImageSanitizer
{
    public const SUPPORTED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    private const JPEG_QUALITY = 92;
    private const WEBP_QUALITY = 90;
    private const PNG_COMPRESSION = 6;

    /**
     * Une photo de 24 Mpx prend dans les 100 Mo une fois décodée, d'où cette marge le temps du traitement.
     */
    private const MEMORY_LIMIT = '512M';

    /**
     * Réécrit le fichier sur place, débarrassé de ses métadonnées.
     *
     * @throws \RuntimeException si l'image ne peut pas être lue ou réécrite
     */
    public function sanitize(string $path): void
    {
        $mimeType = $this->detectMimeType($path);
        if (!in_array($mimeType, self::SUPPORTED_MIME_TYPES, true)) {
            throw new \RuntimeException(sprintf('Type d\'image non supporté : %s', $mimeType));
        }

        $this->raiseMemoryLimit();

        $content = @file_get_contents($path);
        $image = false !== $content ? @imagecreatefromstring($content) : false;
        if (false === $image) {
            throw new \RuntimeException('Image illisible ou corrompue');
        }

        if ('image/jpeg' === $mimeType) {
            $image = $this->applyExifOrientation($image, $path);
        }

        $written = match ($mimeType) {
            'image/jpeg' => imagejpeg($image, $path, self::JPEG_QUALITY),
            'image/webp' => imagewebp($image, $path, self::WEBP_QUALITY),
            'image/png' => $this->writePng($image, $path),
        };

        if (!$written) {
            throw new \RuntimeException('Impossible de réécrire l\'image');
        }
    }

    private function detectMimeType(string $path): string
    {
        $info = @getimagesize($path);

        return false === $info ? 'unknown' : $info['mime'];
    }

    private function applyExifOrientation(\GdImage $image, string $path): \GdImage
    {
        if (!function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        // Attention, imagerotate tourne dans le sens inverse des aiguilles d'une montre.
        $steps = match ($orientation) {
            2 => [[null, IMG_FLIP_HORIZONTAL]],
            3 => [[180, null]],
            4 => [[null, IMG_FLIP_VERTICAL]],
            5 => [[-90, IMG_FLIP_HORIZONTAL]],
            6 => [[-90, null]],
            7 => [[-90, IMG_FLIP_VERTICAL]],
            8 => [[90, null]],
            default => [],
        };

        foreach ($steps as [$angle, $flip]) {
            if (null !== $angle) {
                $rotated = imagerotate($image, $angle, 0);
                if (false !== $rotated) {
                    $image = $rotated;
                }
            }
            if (null !== $flip) {
                imageflip($image, $flip);
            }
        }

        return $image;
    }

    private function writePng(\GdImage $image, string $path): bool
    {
        imagealphablending($image, false);
        imagesavealpha($image, true);

        return imagepng($image, $path, self::PNG_COMPRESSION);
    }

    private function raiseMemoryLimit(): void
    {
        $current = ini_get('memory_limit');
        if ('-1' === $current) {
            return;
        }

        if ($this->toBytes($current) < $this->toBytes(self::MEMORY_LIMIT)) {
            ini_set('memory_limit', self::MEMORY_LIMIT);
        }
    }

    private function toBytes(string $value): int
    {
        $value = trim($value);
        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
