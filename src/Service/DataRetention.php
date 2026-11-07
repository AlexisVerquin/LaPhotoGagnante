<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Contact;
use App\Entity\Theme;
use App\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Applique les durées de conservation promises dans la politique de confidentialité.
 * Si vous changez une durée ici, changez-la aussi dans templates/main/rgpd.html.twig.
 */
final class DataRetention
{
    /** Délai de grâce si le gagnant d'un concours terminé n'a pas pu être désigné. */
    public const PHOTOS_WITHOUT_WINNER_DAYS = 7;
    public const WINNING_PHOTO_KEPT = '1 year';
    public const CLOSED_MESSAGE_KEPT = '24 hours';
    public const OPEN_MESSAGE_KEPT = '1 year';

    public function __construct(
        private EntityManagerInterface $em,
        private DataEraser $eraser,
    ) {
    }

    /**
     * @return array{photos: int, messages: int, accounts: int}
     */
    public function purge(\DateTimeImmutable $now = new \DateTimeImmutable(), bool $dryRun = false): array
    {
        return [
            'photos' => $this->purgePhotos($now, $dryRun),
            'messages' => $this->purgeMessages($now, $dryRun),
            'accounts' => $this->purgeOldAnonymizedAccounts($dryRun),
        ];
    }

    private function purgePhotos(\DateTimeImmutable $now, bool $dryRun): int
    {
        $today = \DateTime::createFromImmutable($now->setTime(0, 0));
        $themes = $this->em->getRepository(Theme::class)->createQueryBuilder('t')
            ->andWhere('t.dateEnd < :today')
            ->setParameter('today', $today, Types::DATE_MUTABLE)
            ->getQuery()
            ->getResult();

        $count = 0;
        /** @var Theme $theme */
        foreach ($themes as $theme) {
            $ended = \DateTimeImmutable::createFromMutable($theme->getDateEnd());
            $winnerDecided = null !== $theme->getWinner()
                || $ended < $now->modify(sprintf('-%d days', self::PHOTOS_WITHOUT_WINNER_DAYS));
            $winnerExpired = $ended < $now->modify('-' . self::WINNING_PHOTO_KEPT);

            foreach ($theme->getPhotos()->toArray() as $photo) {
                $isWinningPhoto = null !== $theme->getWinner() && $photo->getUser() === $theme->getWinner();
                $toDelete = $isWinningPhoto ? $winnerExpired : $winnerDecided;

                if ($toDelete) {
                    ++$count;
                    if (!$dryRun) {
                        $this->eraser->erasePhoto($photo);
                    }
                }
            }
        }

        return $count;
    }

    private function purgeMessages(\DateTimeImmutable $now, bool $dryRun): int
    {
        $ids = $this->em->createQueryBuilder()
            ->select('c.id')
            ->from(Contact::class, 'c')
            ->where('c.type = :type')
            ->andWhere('c.closedAt < :closedLimit OR c.created_at < :openLimit')
            ->setParameter('type', Contact::TYPE_CONTACT)
            ->setParameter('closedLimit', $now->modify('-' . self::CLOSED_MESSAGE_KEPT))
            ->setParameter('openLimit', $now->modify('-' . self::OPEN_MESSAGE_KEPT))
            ->getQuery()
            ->getSingleColumnResult();

        if ([] !== $ids && !$dryRun) {
            // Le message du visiteur et les réponses qu'on lui a envoyées
            $this->em->createQueryBuilder()
                ->delete(Contact::class, 'c')
                ->where('c.id IN (:ids)')
                ->orWhere('c.type = :reply AND c.origin IN (:ids)')
                ->setParameter('ids', $ids)
                ->setParameter('reply', Contact::TYPE_REPLY)
                ->getQuery()
                ->execute();
        }

        return count($ids);
    }

    /**
     * Avant, supprimer son compte l'anonymisait seulement : on finit le travail pour ces comptes-là.
     */
    private function purgeOldAnonymizedAccounts(bool $dryRun): int
    {
        $accounts = $this->em->getRepository(User::class)->findBy(['isActive' => false]);

        if (!$dryRun) {
            foreach ($accounts as $user) {
                $this->eraser->eraseAccount($user);
            }
        }

        return count($accounts);
    }
}
