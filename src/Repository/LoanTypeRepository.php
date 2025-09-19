<?php

namespace App\Repository;

use App\Entity\LoanType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LoanType>
 *
 * @method LoanType|null find($id, $lockMode = null, $lockVersion = null)
 * @method LoanType|null findOneBy(array $criteria, array $orderBy = null)
 * @method LoanType[]    findAll()
 * @method LoanType[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LoanTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LoanType::class);
    }

    public function save(LoanType $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(LoanType $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find active loan types ordered by sort order
     *
     * @return LoanType[]
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('lt')
            ->where('lt.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('lt.sortOrder', 'ASC')
            ->addOrderBy('lt.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find by code
     */
    public function findOneByCode(string $code): ?LoanType
    {
        return $this->createQueryBuilder('lt')
            ->where('lt.code = :code')
            ->setParameter('code', $code)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
