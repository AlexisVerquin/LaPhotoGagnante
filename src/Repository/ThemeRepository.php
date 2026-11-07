<?php

namespace App\Repository;

use App\Entity\Theme;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @extends ServiceEntityRepository<Theme>
 */
class ThemeRepository extends ServiceEntityRepository implements ResetInterface
{
    /**
     * Le concours en cours est demandé une dizaine de fois par page (navbar, compte à rebours…) :
     * on le garde le temps de la requête. Clé = jour, pour qu'un long processus ne garde pas celui de la veille.
     *
     * @var array{day: string, theme: ?Theme}|null
     */
    private ?array $actualTheme = null;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Theme::class);
    }

    public function findActualTheme(): ?Theme
    {
        $now = new \DateTime();
        $day = $now->format('Y-m-d');
        $cached = $this->actualTheme;

        // Un objet que Doctrine a oublié (après un clear()) serait inutilisable : on le recharge
        if (null !== $cached && $cached['day'] === $day
            && (null === $cached['theme'] || $this->getEntityManager()->contains($cached['theme']))) {
            return $cached['theme'];
        }

        $theme = $this->createQueryBuilder('t')
            ->andWhere('t.dateStart <= :now')
            ->andWhere('t.dateEnd >= :now')
            // Les dates sont de type DATE : comparées à un DATETIME,
            // le dernier jour du concours passerait à la trappe.
            ->setParameter('now', $now, Types::DATE_MUTABLE)
            ->orderBy('t.dateStart', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
        $this->actualTheme = ['day' => $day, 'theme' => $theme];

        return $theme;
    }

    /**
     * Appelé entre deux requêtes par Symfony, et dès qu'un concours change (cf. ThemeCacheInvalidator).
     */
    public function reset(): void
    {
        $this->actualTheme = null;
    }

    public function findLastFinishedTheme(): ?Theme
    {
        $now = new \DateTime();

        return $this->createQueryBuilder('t')
            ->andWhere('t.dateEnd < :now')
            ->setParameter('now', $now, Types::DATE_MUTABLE)
            ->orderBy('t.dateEnd', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
}
