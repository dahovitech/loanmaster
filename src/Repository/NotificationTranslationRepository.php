<?php

namespace App\Repository;

use App\Entity\NotificationTranslation;
use App\Entity\Notification;
use App\Entity\Language;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NotificationTranslation>
 *
 * @method NotificationTranslation|null find($id, $lockMode = null, $lockVersion = null)
 * @method NotificationTranslation|null findOneBy(array $criteria, array $orderBy = null)
 * @method NotificationTranslation[]    findAll()
 * @method NotificationTranslation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NotificationTranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationTranslation::class);
    }

    /**
     * Trouve une traduction pour une notification et une langue donnée
     */
    public function findByNotificationAndLanguage(Notification $notification, Language $language): ?NotificationTranslation
    {
        return $this->createQueryBuilder('nt')
            ->andWhere('nt.translatable = :notification')
            ->andWhere('nt.language = :language')
            ->setParameter('notification', $notification)
            ->setParameter('language', $language)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve toutes les traductions d'une notification
     *
     * @return NotificationTranslation[]
     */
    public function findByNotification(Notification $notification): array
    {
        return $this->createQueryBuilder('nt')
            ->andWhere('nt.translatable = :notification')
            ->setParameter('notification', $notification)
            ->orderBy('nt.language', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve toutes les traductions dans une langue donnée
     *
     * @return NotificationTranslation[]
     */
    public function findByLanguage(Language $language): array
    {
        return $this->createQueryBuilder('nt')
            ->andWhere('nt.language = :language')
            ->setParameter('language', $language)
            ->orderBy('nt.subject', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte le nombre de traductions pour une notification
     */
    public function countByNotification(Notification $notification): int
    {
        return $this->createQueryBuilder('nt')
            ->select('COUNT(nt.id)')
            ->andWhere('nt.translatable = :notification')
            ->setParameter('notification', $notification)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouve les notifications qui n'ont pas de traduction dans une langue donnée
     *
     * @return Notification[]
     */
    public function findNotificationsWithoutTranslation(Language $language): array
    {
        $entityManager = $this->getEntityManager();

        return $entityManager->createQueryBuilder()
            ->select('n')
            ->from(Notification::class, 'n')
            ->leftJoin(NotificationTranslation::class, 'nt', 'WITH', 'nt.translatable = n.id AND nt.language = :language')
            ->where('nt.id IS NULL')
            ->setParameter('language', $language)
            ->getQuery()
            ->getResult();
    }

    /**
     * Sauvegarde une entité
     */
    public function save(NotificationTranslation $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime une entité
     */
    public function remove(NotificationTranslation $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
