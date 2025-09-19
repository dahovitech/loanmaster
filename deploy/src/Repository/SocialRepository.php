<?php

namespace App\Repository;

use App\Entity\Social;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Social>
 */
class SocialRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Social::class);
    }

    /**
     * @return Step[] Returns an array of Step objects
     */
    public function findByPublish(): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.isEnabled = :val1')
            ->setParameter('val1', true)
            ->orderBy('e.position', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }
    //    /**
    //     * @return Social[] Returns an array of Social objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('s.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Social
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
