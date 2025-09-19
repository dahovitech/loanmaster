<?php

namespace App\Repository;

use App\Entity\SeoTranslation;
use App\Entity\Seo;
use App\Entity\Language;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SeoTranslation>
 *
 * @method SeoTranslation|null find($id, $lockMode = null, $lockVersion = null)
 * @method SeoTranslation|null findOneBy(array $criteria, array $orderBy = null)
 * @method SeoTranslation[]    findAll()
 * @method SeoTranslation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SeoTranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SeoTranslation::class);
    }

    /**
     * Trouve une traduction pour un SEO et une langue donnée
     */
    public function findBySeoAndLanguage(Seo $seo, Language $language): ?SeoTranslation
    {
        return $this->createQueryBuilder('st')
            ->andWhere('st.seo = :seo')
            ->andWhere('st.language = :language')
            ->setParameter('seo', $seo)
            ->setParameter('language', $language)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve toutes les traductions d'un SEO
     *
     * @return SeoTranslation[]
     */
    public function findBySeo(Seo $seo): array
    {
        return $this->createQueryBuilder('st')
            ->andWhere('st.seo = :seo')
            ->setParameter('seo', $seo)
            ->orderBy('st.language', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve toutes les traductions dans une langue donnée
     *
     * @return SeoTranslation[]
     */
    public function findByLanguage(Language $language): array
    {
        return $this->createQueryBuilder('st')
            ->andWhere('st.language = :language')
            ->setParameter('language', $language)
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte le nombre de traductions pour un SEO
     */
    public function countBySeo(Seo $seo): int
    {
        return $this->createQueryBuilder('st')
            ->select('COUNT(st.id)')
            ->andWhere('st.seo = :seo')
            ->setParameter('seo', $seo)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouve les SEOs qui n'ont pas de traduction dans une langue donnée
     *
     * @return Seo[]
     */
    public function findSeosWithoutTranslation(Language $language): array
    {
        $entityManager = $this->getEntityManager();

        return $entityManager->createQueryBuilder()
            ->select('s')
            ->from(Seo::class, 's')
            ->leftJoin(SeoTranslation::class, 'st', 'WITH', 'st.seo = s.id AND st.language = :language')
            ->where('st.id IS NULL')
            ->setParameter('language', $language)
            ->getQuery()
            ->getResult();
    }

    /**
     * Sauvegarde une entité
     */
    public function save(SeoTranslation $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime une entité
     */
    public function remove(SeoTranslation $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
