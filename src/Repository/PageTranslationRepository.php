<?php

namespace App\Repository;

use App\Entity\PageTranslation;
use App\Entity\Page;
use App\Entity\Language;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PageTranslation>
 *
 * @method PageTranslation|null find($id, $lockMode = null, $lockVersion = null)
 * @method PageTranslation|null findOneBy(array $criteria, array $orderBy = null)
 * @method PageTranslation[]    findAll()
 * @method PageTranslation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PageTranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PageTranslation::class);
    }

    /**
     * Trouve une traduction pour une page et une langue donnée
     */
    public function findByPageAndLanguage(Page $page, Language $language): ?PageTranslation
    {
        return $this->createQueryBuilder('pt')
            ->andWhere('pt.page = :page')
            ->andWhere('pt.language = :language')
            ->setParameter('page', $page)
            ->setParameter('language', $language)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve toutes les traductions d'une page
     *
     * @return PageTranslation[]
     */
    public function findByPage(Page $page): array
    {
        return $this->createQueryBuilder('pt')
            ->andWhere('pt.page = :page')
            ->setParameter('page', $page)
            ->orderBy('pt.language', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve toutes les traductions dans une langue donnée
     *
     * @return PageTranslation[]
     */
    public function findByLanguage(Language $language): array
    {
        return $this->createQueryBuilder('pt')
            ->andWhere('pt.language = :language')
            ->setParameter('language', $language)
            ->orderBy('pt.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte le nombre de traductions pour une page
     */
    public function countByPage(Page $page): int
    {
        return $this->createQueryBuilder('pt')
            ->select('COUNT(pt.id)')
            ->andWhere('pt.page = :page')
            ->setParameter('page', $page)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouve les pages qui n'ont pas de traduction dans une langue donnée
     *
     * @return Page[]
     */
    public function findPagesWithoutTranslation(Language $language): array
    {
        $entityManager = $this->getEntityManager();

        return $entityManager->createQueryBuilder()
            ->select('p')
            ->from(Page::class, 'p')
            ->leftJoin(PageTranslation::class, 'pt', 'WITH', 'pt.page = p.id AND pt.language = :language')
            ->where('pt.id IS NULL')
            ->setParameter('language', $language)
            ->getQuery()
            ->getResult();
    }

    /**
     * Sauvegarde une entité
     */
    public function save(PageTranslation $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime une entité
     */
    public function remove(PageTranslation $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
