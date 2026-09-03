<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserInfoType;
use App\Constant\AlertConst;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class UserManageController extends AbstractController
{
    #[Route('/user/manage', name: 'app_user_manage')]
    public function index(
        Request $request,
        EntityManagerInterface $em,
        TranslatorInterface $translator,
        UserPasswordHasherInterface  $userPasswordHasher
    ): Response {
        $user = $this->getUser();
        $form = $this->createForm(UserInfoType::class, $this->getUser());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            if ($user->getPassword() && $userPasswordHasher->isPasswordValid($user, $user->getPassword())) {
                $user->setPassword($userPasswordHasher->hashPassword($user, $user->getPassword()));
            }

            $em->flush();

            $this->addFlash(AlertConst::ALERT_SUCCESS, $translator->trans('alert.modification.success') );
            return $this->redirectToRoute('app_user_manage');
        }

        return $this->render('user_manage/index.html.twig', [
            'formUser' => $form,
        ]);
    }


    #[Route('/user/manage/uploadAvatar', name: 'app_user_manage_uploadAvatar')]
    public function uploadAvatar(
        Request $request,
        SluggerInterface $slugger,
        EntityManagerInterface $em,
        #[Autowire('%kernel.project_dir%/public/uploads/avatars')] string $avatarFileDirectory,
    ): Response {
        $submittedToken = $request->headers->get('X-CSRF-Token');

        if (!$this->isCsrfTokenValid('upload-avatar', $submittedToken)) {
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

        // 5. Déplacement du fichier
        try {
            $file->move(
                $avatarFileDirectory,
                $newFilename
            );
        } catch (FileException $e) {
            return $this->json(['error' => 'Erreur lors de l\'enregistrement du fichier'], 500);
        }

        $user = $this->getUser();
        $user->setAvatar($newFilename);

        $em->flush();

        return $this->json(['success' => true]);
    }
}
