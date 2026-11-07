<?php

namespace App\Controller;

use App\Entity\Photo;
use App\Entity\Theme;
use App\Entity\User;
use App\Service\ImageSanitizer;
use App\Service\PhotoStorage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

final class PhotoController extends AbstractController
{
    public function __construct(
        //        private TranslatorInterface $translator,
        private EntityManagerInterface $em,
    ) {
    }

    #[Route('/upload/photo_challenge', name: 'app_photo_chanllenge_update', methods: ['POST'])]
    public function index(
        Request $request,
        SluggerInterface $slugger,
        PhotoStorage $photoStorage,
        LoggerInterface $logger,
        ImageSanitizer $imageSanitizer,
    ): Response {
        $submittedToken = $request->headers->get('X-CSRF-Token');

        if (!$this->isCsrfTokenValid('upload-photo-theme', $submittedToken)) {
            return $this->json(['error' => 'Token CSRF invalide'], 419);
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Vous devez être connecté pour participer'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$user->isVerified()) {
            return $this->json(['error' => 'Vous devez confirmer votre adresse email pour participer'], Response::HTTP_FORBIDDEN);
        }

        $infoTheme = $this->em->getRepository(Theme::class)->findActualTheme();
        if (!$infoTheme) {
            return $this->json(['error' => 'Aucun concours en cours'], Response::HTTP_BAD_REQUEST);
        }

        $alreadyPosted = $this->em->getRepository(Photo::class)->findOneBy(['user' => $user, 'theme' => $infoTheme]);
        if ($alreadyPosted) {
            return $this->json(['error' => 'Vous avez déjà envoyé une photo pour ce concours'], Response::HTTP_CONFLICT);
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
            // La photo reste hors de portée du public tant qu'un admin ne l'a pas validée.
            $movedFile = $photoStorage->storePending($file, $infoTheme, $newFilename);
        } catch (FileException $e) {
            $logger->error($e->getMessage());

            return $this->json(['error' => 'Erreur lors de l\'enregistrement du fichier'], 500);
        }

        // On efface les EXIF (dont la position GPS) avant que quiconque puisse voir la photo.
        try {
            $imageSanitizer->sanitize($movedFile->getPathname());
        } catch (\RuntimeException $e) {
            $logger->warning($e->getMessage());
            @unlink($movedFile->getPathname());

            return $this->json(['error' => 'Image illisible, merci d\'essayer avec un autre fichier'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $photo = new Photo();
        $photo->setTheme($infoTheme);
        $photo->setUser($user);
        $photo->setIsVisible(false);
        $photo->setName($newFilename);
        $this->em->persist($photo);
        $this->em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Photo envoyée ! Elle sera visible après validation par un administrateur.',
        ]);
    }

    /**
     * Sert l'image d'une photo. Tant qu'elle n'est pas validée, seuls son auteur et les admins la voient :
     * les autres reçoivent une 404, pour ne même pas savoir qu'elle existe.
     */
    #[Route('/photo/{id}/image', name: 'app_photo_image', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function image(Photo $photo, PhotoStorage $photoStorage): Response
    {
        $user = $this->getUser();
        $isOwner = $user instanceof User && $photo->getUser()?->getId() === $user->getId();

        if (!$photo->isVisible() && !$isOwner && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createNotFoundException();
        }

        $path = $photoStorage->locate($photo);
        if (null === $path) {
            throw $this->createNotFoundException();
        }

        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        if ($photo->isVisible()) {
            $response->setPublic();
            $response->setMaxAge(3600);
        } else {
            $response->setPrivate();
            $response->headers->addCacheControlDirective('no-store');
        }

        return $response;
    }
}
