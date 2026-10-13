<?php

namespace App\Controller;

use App\Entity\Photo;
use App\Entity\Theme;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

final class PhotoController extends AbstractController
{
    public function __construct(
//        private TranslatorInterface $translator,
        private EntityManagerInterface $em,
    ) {}
    #[Route('/upload/photo_challenge', name: 'app_photo_chanllenge_update')]
    public function index(
        Request $request,
        SluggerInterface $slugger,
        #[Autowire('%kernel.project_dir%/public/uploads/themes')] string $photoFileDirectory,
        LoggerInterface $logger,

    ): Response
    {
        $submittedToken = $request->headers->get('X-CSRF-Token');
        $infoTheme = $this->em->getRepository(Theme::class)->findActualTheme();

        if (!$this->isCsrfTokenValid('upload-photo-theme', $submittedToken)) {
            return $this->json(['error' => 'Token CSRF invalide'], 419);
        }

        /** @var UploadedFile|null $file */
        $file = $request->files->get('file');

        if (!$file) {
            return $this->json(['error' => 'Aucun fichier reçu'], 400);
        }

        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($file->getMimeType(), $allowedMimeTypes, true)) {
            return $this->json(['error' => 'Type de fichier non autorisé'], 415);
        }

        $maxSize = 5 * 1024 * 1024; // 5 Mo
        if ($file->getSize() > $maxSize) {
            return $this->json(['error' => 'Fichier trop volumineux'], 413);
        }

        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $slugger->slug($originalFilename);
        $newFilename = sprintf('%s-%s.%s', $safeFilename, uniqid(), $file->guessExtension());

        try {
            $file->move(
                $photoFileDirectory.'/'.$infoTheme->getDirectoryName(),
                $newFilename
            );
        } catch (FileException $e) {
            $logger->error($e->getMessage());
            return $this->json(['error' => 'Erreur lors de l\'enregistrement du fichier'], 500);
        }

        $user = $this->getUser();
        $photo = new Photo();
        $photo->setTheme($infoTheme);
        $photo->setUser($user);
        $photo->setIsVisible(false);
        $photo->setName($newFilename);
        $this->em->persist($photo);
        $this->em->flush();

        return $this->redirectToRoute('app_main');
    }
}
