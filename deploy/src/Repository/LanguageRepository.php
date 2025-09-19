<?php

namespace App\Repository;

use App\Entity\Language;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Language>
 */
class LanguageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Language::class);
    }

    /**
     * @return Language[] Returns an array of Language objects
     */
    public function findByPublishNotDefault(): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.isDefault = :val1')
            ->andWhere('l.isEnabled = :val2')
            ->setParameter('val1', false)
            ->setParameter('val2', true)
            ->getQuery()
            ->getResult()
        ;
    }
    
    /**
     * @return Language[] Returns an array of Page objects
     */
    public function findByPublish(): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.isEnabled = :val1')
            ->setParameter('val1', true)
            ->getQuery()
            ->getResult()
        ;
    }
    
    //    /**
    //     * @return Language[] Returns an array of Language objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('l')
    //            ->andWhere('l.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('l.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Language
    //    {
    //        return $this->createQueryBuilder('l')
    //            ->andWhere('l.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
