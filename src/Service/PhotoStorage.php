<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Photo;
use App\Entity\Theme;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Range les photos de concours : en attente, elles restent hors du web (storage/pending),
 * et une fois validées elles passent dans public/uploads.
 */
final class PhotoStorage
{
    private Filesystem $filesystem;

    public function __construct(
        #[Autowire('%app.pending_photos_dir%')]
        private string $pendingDirectory,
        #[Autowire('%app.public_photos_dir%')]
        private string $publicDirectory,
    ) {
        $this->filesystem = new Filesystem();
    }

    /**
     * Met la photo envoyée de côté en attendant qu'un admin la valide.
     */
    public function storePending(UploadedFile $file, Theme $theme, string $filename): File
    {
        return $file->move($this->pendingDirectory . '/' . $theme->getDirectoryName(), $filename);
    }

    /**
     * Publie la photo validée : son fichier rejoint public/.
     *
     * @throws \RuntimeException si le fichier est introuvable
     */
    public function publish(Photo $photo): void
    {
        $public = $this->publicPath($photo);
        if (is_file($public)) {
            return; // déjà publique (photos envoyées avant la mise en place de la zone privée)
        }

        $pending = $this->pendingPath($photo);
        if (!is_file($pending)) {
            throw new \RuntimeException(sprintf('Fichier introuvable pour la photo #%d', $photo->getId()));
        }

        $this->move($pending, $public);
    }

    /**
     * Le chemin inverse, pour les vieilles photos en attente restées dans public/.
     *
     * @return bool true si un fichier a été déplacé
     */
    public function unpublish(Photo $photo): bool
    {
        $public = $this->publicPath($photo);
        if (!is_file($public)) {
            return false;
        }

        $this->move($public, $this->pendingPath($photo));

        return true;
    }

    /**
     * Où se trouve le fichier, qu'il soit en attente ou publié (null s'il a disparu).
     */
    /**
     * Efface le fichier de la photo, qu'il soit en attente ou publié.
     */
    public function delete(Photo $photo): void
    {
        $this->filesystem->remove([$this->pendingPath($photo), $this->publicPath($photo)]);
    }

    public function locate(Photo $photo): ?string
    {
        foreach ([$this->pendingPath($photo), $this->publicPath($photo)] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    public function pendingPath(Photo $photo): string
    {
        return $this->pendingDirectory . '/' . $this->relativePath($photo);
    }

    public function publicPath(Photo $photo): string
    {
        return $this->publicDirectory . '/' . $this->relativePath($photo);
    }

    private function move(string $from, string $to): void
    {
        // rename() ne crée pas le dossier, ce qui arrive pour la première photo d'un concours.
        $this->filesystem->mkdir(dirname($to));
        $this->filesystem->rename($from, $to, true);
    }

    private function relativePath(Photo $photo): string
    {
        // basename() pour qu'un nom stocké en base ne puisse jamais remonter avec « ../ ».
        return basename((string) $photo->getTheme()?->getDirectoryName()) . '/' . basename((string) $photo->getName());
    }
}
