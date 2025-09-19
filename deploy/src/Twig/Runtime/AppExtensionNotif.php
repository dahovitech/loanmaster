<?php

namespace App\Twig\Runtime;

use App\Entity\Notification;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Extension\RuntimeExtensionInterface;

class AppExtensionNotif implements RuntimeExtensionInterface
{
   /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * AppExtension constructor.
     */
    public function __construct(EntityManagerInterface $entityManager)
    {

        $this->entityManager = $entityManager;
    }

    public function onNotifCount($user)
    {
        $notifs = $this->entityManager->getRepository(Notification::class)->findByNotSee($user);
        
        
        return count($notifs);
    }
}
