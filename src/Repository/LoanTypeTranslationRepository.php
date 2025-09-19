<?php

namespace App\Repository;

use App\Entity\LoanTypeTranslation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LoanTypeTranslation>
 *
 * @method LoanTypeTranslation|null find($id, $lockMode = null, $lockVersion = null)
 * @method LoanTypeTranslation|null findOneBy(array $criteria, array $orderBy = null)
 * @method LoanTypeTranslation[]    findAll()
 * @method LoanTypeTranslation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LoanTypeTranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LoanTypeTranslation::class);
    }

    public function save(LoanTypeTranslation $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(LoanTypeTranslation $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
