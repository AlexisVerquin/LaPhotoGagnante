<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserInfoType;
use App\Security\EmailVerifier;
use App\Service\DataEraser;
use App\Service\ImageSanitizer;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class UserManageController extends AbstractController
{
    #[Route('/user/manage', name: 'app_user_manage')]
    public function index(
        Request $request,
        EntityManagerInterface $em,
        TranslatorInterface $translator,
        UserPasswordHasherInterface $userPasswordHasher,
        EmailVerifier $emailVerifier,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $email = $user->getEmail();
        $form = $this->createForm(UserInfoType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $currentPassword = (string) $form->get('password')->getData();
            $newPassword = (string) $form->get('new_password')->getData();
            $confirmPassword = (string) $form->get('password_confirm')->getData();

            if ('' !== $newPassword || '' !== $currentPassword) {
                if (!$userPasswordHasher->isPasswordValid($user, $currentPassword)) {
                    $form->get('password')->addError(new FormError($translator->trans('alert.password.invalid_current')));
                } elseif ($newPassword !== $confirmPassword) {
                    $form->get('password_confirm')->addError(new FormError($translator->trans('alert.password.mismatch')));
                } else {
                    $user->setPassword($userPasswordHasher->hashPassword($user, $newPassword));
                }
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            if ($email !== $user->getEmail()) {
                $user->setIsVerified(false);
                $emailVerifier->sendEmailConfirmation(
                    'app_verify_email',
                    $user,
                    new TemplatedEmail()
                        ->to((string) $user->getEmail())
                        ->subject('Please Confirm your Email')
                        ->htmlTemplate('user_manage/confirmation_email.html.twig'),
                );
            }

            $em->flush();

            $this->addFlash('success', $translator->trans('alert.modification.success'));

            return $this->redirectToRoute('app_user_manage');
        }

        if ($form->isSubmitted()) {
            // Le formulaire a déjà modifié l'utilisateur en mémoire : on recharge la base
            // pour ne pas garder en session des valeurs qui ont été refusées.
            $em->refresh($user);
        }

        return $this->render('user_manage/index.html.twig', [
            'formUser' => $form,
        ]);
    }

    #[Route('/user/manage/uploadAvatar', name: 'app_user_manage_uploadAvatar', methods: ['POST'])]
    public function uploadAvatar(
        LoggerInterface $logger,
        Request $request,
        SluggerInterface $slugger,
        EntityManagerInterface $em,
        ImageSanitizer $imageSanitizer,
        #[Autowire('%kernel.project_dir%/public/uploads/avatars')]
        string $avatarFileDirectory,
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
            $movedFile = $file->move(
                $avatarFileDirectory,
                $newFilename,
            );
        } catch (FileException $e) {
            $logger->error($e->getMessage());

            return $this->json(['error' => 'Erreur lors de l\'enregistrement du fichier'], 500);
        }

        // Pas de position GPS ni d'infos d'appareil dans les avatars non plus.
        try {
            $imageSanitizer->sanitize($movedFile->getPathname());
        } catch (\RuntimeException $e) {
            $logger->warning($e->getMessage());
            @unlink($movedFile->getPathname());

            return $this->json(['error' => 'Image illisible, merci d\'essayer avec un autre fichier'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Vous devez être connecté'], Response::HTTP_UNAUTHORIZED);
        }

        // On jette l'ancien avatar, inutile de le garder sur le disque.
        $oldAvatar = $user->getAvatar();
        if ($oldAvatar) {
            new Filesystem()->remove($avatarFileDirectory . '/' . basename($oldAvatar));
        }

        $user->setAvatar($newFilename);

        $em->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/user/delete', name: 'app_delete_user', methods: ['DELETE'])]
    public function removeUser(
        LoggerInterface $logger,
        Request $request,
        Security $security,
        DataEraser $dataEraser,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ($this->isCsrfTokenValid('delete-user-' . $user->getId(), $request->request->getString('_token'))) {
            try {
                // Tout part : compte, photos, votes, messages et avatar (cf. DataEraser)
                $dataEraser->eraseAccount($user);
                $security->logout(false);

                $this->addFlash('success', 'Votre compte et vos données ont bien été supprimés. Merci pour votre participation, et à bientôt !');

                return $this->redirectToRoute('app_main');
            } catch (\Exception $e) {
                $logger->error($e->getMessage());
                $this->addFlash('danger', 'Erreur lors de la suppression de votre compte, merci de nous contacter.');
            }
        }

        return $this->redirectToRoute('app_user_manage');
    }
}
