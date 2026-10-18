<?php

namespace App\Controller;

use App\Constant\AlertConst;
use App\Entity\User;
use App\Form\UserInfoType;
use App\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
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
        /** @var User|null $user */
        $user = $this->getUser();

        $email = $user->getEmail();
        $form = $this->createForm(UserInfoType::class, $this->getUser());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($user->getPassword() && $userPasswordHasher->isPasswordValid($user, $user->getPassword())) {
                $user->setPassword($userPasswordHasher->hashPassword($user, $user->getPassword()));
            }

            if ($email !== $user->getEmail()) {
                $user->setIsVerified(false);
                $emailVerifier->sendEmailConfirmation('app_verify_email', $user,
                    new TemplatedEmail()
                        ->from(new Address('no-reply@${PROJECT_NAME}.fr', '${PROJECT_NAME}'))
                        ->to((string) $user->getEmail())
                        ->subject('Please Confirm your Email')
                        ->htmlTemplate('user_manage/confirmation_email.html.twig'),
                );
            }

            $em->flush();

            $this->addFlash('alert-success', $translator->trans('alert.modification.success'));

            return $this->redirectToRoute('app_user_manage');
        }

        return $this->render('user_manage/index.html.twig', [
            'formUser' => $form,
        ]);
    }

    #[Route('/user/manage/uploadAvatar', name: 'app_user_manage_uploadAvatar')]
    public function uploadAvatar(
        LoggerInterface $logger,
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
                $newFilename,
            );
        } catch (FileException $e) {
            $logger->error($e->getMessage());

            return $this->json(['error' => 'Erreur lors de l\'enregistrement du fichier'], 500);
        }

        /** @var User|null $user */
        $user = $this->getUser();
        $user->setAvatar($newFilename);

        $em->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/user/delete', name: 'app_delete_user', methods: ['delete'])]
    public function removeUser(
        LoggerInterface $logger,
        EntityManagerInterface $em,
        Request $request,
        Filesystem $filesystem,
        #[Autowire('%kernel.project_dir%/public/uploads/avatars')] string $avatarFileDirectory,
    ): Response {
        $user = $this->getUser();
        if ($this->isCsrfTokenValid('delete-user-' . $user->getId(), $request->request->get('_token'))) {
            /** @var User|null $user */
            $user = $this->getUser();
            $avatar = $user->getAvatar();

            try {
                if ($avatar) {
                    $filesystem->remove($avatarFileDirectory . '/' . $avatar);
                }

                $randomisedMail = uniqid('userdelete-');
                $user
                    ->setEmail($randomisedMail . '@deleteduser.fr')
                    ->setIsVerified(false)
                    ->setUpdatedAt(new \DateTime('now'))
                    ->setRoles(['ROLE_USER'])
                    ->setAvatar('')
                    ->setName('Anonyme')
                    ->setCity('')
                ;

                $em->flush();

                $this->addFlash('success', "Votre compte a bien été supprimer, merci pour votre participation. \n a bientôt");

                return $this->redirectToRoute('app_main');
            } catch (\Exception $e) {
                $logger->error($e->getMessage());
                $this->addFlash('error', "Erreur lors de la suppression de l'utilisateur, veuillez contacter un administrateur");
            }
        }

        return $this->redirectToRoute('app_user_manage');
    }
}
