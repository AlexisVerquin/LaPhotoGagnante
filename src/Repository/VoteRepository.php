<?php

namespace App\Repository;

use App\Entity\Theme;
use App\Entity\Vote;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Vote>
 */
class VoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Vote::class);
    }

    //    /**
    //     * @return Vote[] Returns an array of Vote objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('v')
    //            ->andWhere('v.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('v.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Vote
    //    {
    //        return $this->createQueryBuilder('v')
    //            ->andWhere('v.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    /**
     * Nombre de votes de chaque photo d'un concours, en une seule requête.
     *
     * @return array<int, int> id de la photo => nombre de votes
     */
    public function countByPhotoForTheme(Theme $theme): array
    {
        $rows = $this->createQueryBuilder('v')
            ->select('IDENTITY(v.photo) AS photo', 'COUNT(v.id) AS votes')
            ->andWhere('v.theme = :theme')
            ->setParameter('theme', $theme)
            ->groupBy('v.photo')
            ->getQuery()
            ->getArrayResult();

        return array_column(array_map(static fn (array $r): array => [(int) $r['photo'], (int) $r['votes']], $rows), 1, 0);
    }
}
