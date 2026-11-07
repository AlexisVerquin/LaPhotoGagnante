<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Contact;
use App\Entity\Photo;
use App\Entity\ResetPasswordRequest;
use App\Entity\Theme;
use App\Entity\User;
use App\Entity\Vote;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Le seul endroit qui efface vraiment des données personnelles : une photo, ou un compte entier.
 * La suppression de compte et la purge automatique passent toutes les deux par ici.
 */
final class DataEraser
{
    public function __construct(
        private EntityManagerInterface $em,
        private PhotoStorage $photoStorage,
        #[Autowire('%kernel.project_dir%/public/uploads/avatars')]
        private string $avatarDirectory,
    ) {
    }

    /**
     * Supprime la photo, son fichier et les votes qu'elle avait reçus.
     */
    public function erasePhoto(Photo $photo): void
    {
        $this->removeAll(Vote::class, ['photo' => $photo]);

        $this->photoStorage->delete($photo);
        $photo->getTheme()?->removePhoto($photo);
        $this->em->remove($photo);
        $this->em->flush();
    }

    /**
     * Supprime le compte et tout ce qui s'y rattache.
     * Les concours qu'il avait gagnés restent, mais sans gagnant.
     */
    public function eraseAccount(User $user): void
    {
        foreach ($this->em->getRepository(Photo::class)->findBy(['user' => $user]) as $photo) {
            $this->erasePhoto($photo);
        }

        $this->removeAll(Vote::class, ['user' => $user]);
        $this->removeAll(ResetPasswordRequest::class, ['user' => $user]);
        $this->eraseContactMessages($user);

        foreach ($this->em->getRepository(Theme::class)->findBy(['winner' => $user]) as $theme) {
            $theme->setWinner(null);
        }

        if ($user->getAvatar()) {
            new Filesystem()->remove($this->avatarDirectory . '/' . basename($user->getAvatar()));
        }

        $this->em->remove($user);
        $this->em->flush();
    }

    /**
     * Les messages envoyés par le membre (connecté, ou avec la même adresse), et les réponses qu'on lui a faites.
     */
    private function eraseContactMessages(User $user): void
    {
        $ids = $this->em->createQueryBuilder()
            ->select('c.id')
            ->from(Contact::class, 'c')
            ->where('c.type = :type')
            ->andWhere('c.userId = :id OR c.email = :email')
            ->setParameter('type', Contact::TYPE_CONTACT)
            ->setParameter('id', $user->getId())
            ->setParameter('email', $user->getEmail())
            ->getQuery()
            ->getSingleColumnResult();

        if ([] === $ids) {
            return;
        }

        $this->em->createQueryBuilder()
            ->delete(Contact::class, 'c')
            ->where('c.id IN (:ids)')
            ->orWhere('c.type = :reply AND c.origin IN (:ids)')
            ->setParameter('ids', $ids)
            ->setParameter('reply', Contact::TYPE_REPLY)
            ->getQuery()
            ->execute();
    }

    /**
     * Supprime via l'ORM (et non en SQL direct) pour que Doctrine oublie aussi ces objets en mémoire.
     *
     * @param class-string         $entity
     * @param array<string, mixed> $criteria
     */
    private function removeAll(string $entity, array $criteria): void
    {
        foreach ($this->em->getRepository($entity)->findBy($criteria) as $object) {
            $this->em->remove($object);
        }
    }
}
