<?php

namespace App\Controller\User;

use App\Service\Util;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\NotificationRepository;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route(path: '/{_locale}/account/notification', name: 'app_user_notification_')]
#[IsGranted('ROLE_USER')]
class NotificationController extends AbstractController
{



    public function __construct(
        private TranslatorInterface $translator,
        private Util $util,
        private UrlGeneratorInterface $urlGenerator,
        private NotificationRepository $notificationRepository,
        private EntityManagerInterface $entityManager
    ) {
        $this->translator = $translator;
        $this->util = $util;
    }


    #[Route(path: '/', name: 'index')]
    public function index()
    {

        return $this->render('@admin/notification/index.html.twig', [
            'notifications' => $this->notificationRepository->findByUser($this->getUser())
        ]);
    }

    #[Route(path: '/show/{id}', name: 'show')]
    public function show($id)
    {
        $notification = $this->notificationRepository->find($id);

        $notification->setStatus('see');

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        return $this->render('@admin/notification/show.html.twig', [
            'notification' => $notification
        ]);
    }
}
