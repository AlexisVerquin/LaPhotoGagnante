<?php

namespace App\Repository;

use App\Entity\Theme;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Theme>
 */
class ThemeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Theme::class);
    }

    public function findActualTheme(): ?Theme
    {
        $now = new \DateTime();

        return $this->createQueryBuilder('t')
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
