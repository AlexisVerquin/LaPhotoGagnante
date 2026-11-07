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
 * Emplacement des fichiers des photos de concours :
 * - en attente de validation : hors du dossier web (storage/pending/<theme>/), servies par un contrôleur
 *   qui vérifie les droits (auteur ou admin) ;
 * - validées : déplacées dans public/uploads/themes/<theme>/, servies directement par le serveur web.
 */
final class PhotoStorage
{
    private Filesystem $filesystem;

    public function __construct(
        #[Autowire('%app.pending_photos_dir%')] private string $pendingDirectory,
        #[Autowire('%app.public_photos_dir%')] private string $publicDirectory,
    ) {
        $this->filesystem = new Filesystem();
    }

    /**
     * Range un fichier envoyé dans la zone privée, en attente de validation.
     */
    public function storePending(UploadedFile $file, Theme $theme, string $filename): File
    {
        return $file->move($this->pendingDirectory . '/' . $theme->getDirectoryName(), $filename);
    }

    /**
     * Rend la photo publique (validation admin) en déplaçant son fichier dans public/.
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
     * Déplace le fichier d'une photo non validée de public/ vers la zone privée (photos historiques).
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
     * Chemin du fichier sur le disque, où qu'il soit, ou null s'il n'existe pas.
     */
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
        // rename() ne crée pas le dossier de destination (premier fichier d'un concours)
        $this->filesystem->mkdir(dirname($to));
        $this->filesystem->rename($from, $to, true);
    }

    private function relativePath(Photo $photo): string
    {
        // basename : jamais de « ../ » venant de la base de données
        return basename((string) $photo->getTheme()?->getDirectoryName()) . '/' . basename((string) $photo->getName());
    }
}
