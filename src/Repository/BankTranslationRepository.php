<?php

namespace App\Repository;

use App\Entity\BankTranslation;
use App\Entity\Bank;
use App\Entity\Language;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BankTranslation>
 *
 * @method BankTranslation|null find($id, $lockMode = null, $lockVersion = null)
 * @method BankTranslation|null findOneBy(array $criteria, array $orderBy = null)
 * @method BankTranslation[]    findAll()
 * @method BankTranslation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BankTranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BankTranslation::class);
    }

    /**
     * Trouve une traduction pour une banque et une langue donnée
     */
    public function findByBankAndLanguage(Bank $bank, Language $language): ?BankTranslation
    {
        return $this->createQueryBuilder('bt')
            ->andWhere('bt.translatable = :bank')
            ->andWhere('bt.language = :language')
            ->setParameter('bank', $bank)
            ->setParameter('language', $language)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve toutes les traductions d'une banque
     *
     * @return BankTranslation[]
     */
    public function findByBank(Bank $bank): array
    {
        return $this->createQueryBuilder('bt')
            ->andWhere('bt.translatable = :bank')
            ->setParameter('bank', $bank)
            ->orderBy('bt.language', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve toutes les traductions dans une langue donnée
     *
     * @return BankTranslation[]
     */
    public function findByLanguage(Language $language): array
    {
        return $this->createQueryBuilder('bt')
            ->andWhere('bt.language = :language')
            ->setParameter('language', $language)
            ->orderBy('bt.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte le nombre de traductions pour une banque
     */
    public function countByBank(Bank $bank): int
    {
        return $this->createQueryBuilder('bt')
            ->select('COUNT(bt.id)')
            ->andWhere('bt.translatable = :bank')
            ->setParameter('bank', $bank)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouve les banques qui n'ont pas de traduction dans une langue donnée
     *
     * @return Bank[]
     */
    public function findBanksWithoutTranslation(Language $language): array
    {
        $entityManager = $this->getEntityManager();

        return $entityManager->createQueryBuilder()
            ->select('b')
            ->from(Bank::class, 'b')
            ->leftJoin(BankTranslation::class, 'bt', 'WITH', 'bt.translatable = b.id AND bt.language = :language')
            ->where('bt.id IS NULL')
            ->setParameter('language', $language)
            ->getQuery()
            ->getResult();
    }

    /**
     * Sauvegarde une entité
     */
    public function save(BankTranslation $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime une entité
     */
    public function remove(BankTranslation $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
