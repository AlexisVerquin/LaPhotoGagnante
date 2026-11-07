<?php

namespace App\Repository;

use App\Entity\Photo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Photo>
 */
class PhotoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Photo::class);
    }

    /**
     * Les photos gagnantes encore conservées (moins d'un an), de la plus récente à la plus ancienne.
     *
     * @return list<Photo>
     */
    public function findRecentWinningPhotos(int $limit = 6): array
    {
        return $this->createQueryBuilder('p')
            ->innerJoin('p.theme', 't')
            ->addSelect('t')
            ->andWhere('t.winner = p.user')
            ->andWhere('p.is_visible = true')
            ->orderBy('t.dateEnd', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * La photo qui a reçu le plus de votes aujourd'hui, avec son score du jour.
     *
     * @return list<array{0: Photo, nbVotes: int}>
     */
    public function findPhotosOfTheDay(): array
    {
        $dateStart = new \DateTime();
        $dateEnd = new \DateTime();
        $dateStart->setTime(0, 0, 0);
        $dateEnd->setTime(23, 59, 59);

        return $this->createQueryBuilder('p')
            ->select('p', 'COUNT(v.id) as nbVotes')
            ->innerJoin('p.votes', 'v')
            ->andWhere('v.date BETWEEN :dateStart AND :dateEnd')
            ->setParameter('dateStart', $dateStart)
            ->setParameter('dateEnd', $dateEnd)
            ->groupBy('p.id')
            ->orderBy('nbVotes', 'DESC')
            ->getQuery()
            ->setMaxResults(1)
            ->getResult();
    }

    //    /**
    //     * @return Photo[] Returns an array of Photo objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('p.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Photo
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
