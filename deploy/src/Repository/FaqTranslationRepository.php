<?php

namespace App\Repository;

use App\Entity\FaqTranslation;
use App\Entity\Faq;
use App\Entity\Language;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FaqTranslation>
 *
 * @method FaqTranslation|null find($id, $lockMode = null, $lockVersion = null)
 * @method FaqTranslation|null findOneBy(array $criteria, array $orderBy = null)
 * @method FaqTranslation[]    findAll()
 * @method FaqTranslation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FaqTranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FaqTranslation::class);
    }

    /**
     * Trouve une traduction pour une FAQ et une langue donnée
     */
    public function findByFaqAndLanguage(Faq $faq, Language $language): ?FaqTranslation
    {
        return $this->createQueryBuilder('ft')
            ->andWhere('ft.translatable = :faq')
            ->andWhere('ft.language = :language')
            ->setParameter('faq', $faq)
            ->setParameter('language', $language)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve toutes les traductions d'une FAQ
     *
     * @return FaqTranslation[]
     */
    public function findByFaq(Faq $faq): array
    {
        return $this->createQueryBuilder('ft')
            ->andWhere('ft.translatable = :faq')
            ->setParameter('faq', $faq)
            ->orderBy('ft.language', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve toutes les traductions dans une langue donnée
     *
     * @return FaqTranslation[]
     */
    public function findByLanguage(Language $language): array
    {
        return $this->createQueryBuilder('ft')
            ->andWhere('ft.language = :language')
            ->setParameter('language', $language)
            ->orderBy('ft.question', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte le nombre de traductions pour une FAQ
     */
    public function countByFaq(Faq $faq): int
    {
        return $this->createQueryBuilder('ft')
            ->select('COUNT(ft.id)')
            ->andWhere('ft.translatable = :faq')
            ->setParameter('faq', $faq)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouve les FAQs qui n'ont pas de traduction dans une langue donnée
     *
     * @return Faq[]
     */
    public function findFaqsWithoutTranslation(Language $language): array
    {
        $entityManager = $this->getEntityManager();

        return $entityManager->createQueryBuilder()
            ->select('f')
            ->from(Faq::class, 'f')
            ->leftJoin(FaqTranslation::class, 'ft', 'WITH', 'ft.translatable = f.id AND ft.language = :language')
            ->where('ft.id IS NULL')
            ->setParameter('language', $language)
            ->getQuery()
            ->getResult();
    }

    /**
     * Sauvegarde une entité
     */
    public function save(FaqTranslation $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime une entité
     */
    public function remove(FaqTranslation $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
