<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Service\Util;
use App\Entity\Notification;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\NotificationRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[Route('/admin/kyc', name: 'app_admin_kyc_')]
class AdminKycController extends AbstractController
{
    public function __construct(
        private TranslatorInterface $translator,
        private Util $util,
        private UrlGeneratorInterface $urlGenerator,
        private NotificationRepository $notificationRepository,
        private KernelInterface $kernel,
        private ParameterBagInterface $parameterBag,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/client/kyc/{id}', name: 'client', methods: ['GET', 'POST'])]
    public function showKyc($id, Request $request): Response
    {
        $user = $this->entityManager->getRepository(User::class)->find($id);

        if ($request->isMethod('POST')) {
            $status = $request->request->get('status');
            $message = $request->request->get('message');

            if ($status === 'approved') {
                $user->setVerificationStatus('approved');
                $user->setIsEnabled(true);

                $subject = $this->translator->trans('email.kyc_approved.title', [], null, $user->getLocale());

                $context = ['user' => $user];
                // Envoyer un email de confirmation
                $this->util->sender(
                    $this->util->getSetting()->getEmailSender(),
                    $subject,
                    '@emails/kyc_approved.html.twig',
                    [$user->getEmail()],
                    $context
                );

                $renderedView = $this->renderView('@emails/kyc_approved.html.twig', $context);

                $notif = new Notification();
                $notif->setSubject($subject);
                $notif->setContent($renderedView);
                $notif->setUser($user);
                $notif->setStatus("notsee");
                $this->entityManager->persist($notif);
                $this->entityManager->persist($user);
                $this->entityManager->flush();
                $this->addFlash('success', 'Compte validé avec succès');
            } elseif ($status === 'rejected') {
                $user->setVerificationStatus('rejected');
                $user->setIsEnabled(false);
                $subject = $this->translator->trans('email.kyc_rejected.title', [], null, $user->getLocale());
                $context = ['user' => $user, 'message' => $message];

                // Envoyer un email de rejet avec le message
                $this->util->sender(
                    $this->util->getSetting()->getEmailSender(),
                    $subject,
                    '@emails/kyc_rejected.html.twig',
                    [$user->getEmail()],
                    $context
                );
                $renderedView = $this->renderView('@emails/kyc_rejected.html.twig', $context);

                $notif = new Notification();
                $notif->setSubject($subject);
                $notif->setContent($renderedView);
                $notif->setUser($user);
                $notif->setStatus("notsee");
                $this->entityManager->persist($notif);
                $this->entityManager->persist($user);
                $this->entityManager->flush();
                $this->addFlash('success', 'Compte rejeté avec succès');
            }

            return $this->redirectToRoute('app_admin_clientLists');
        }

        return $this->render('@admin/user/kyc.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/pro/kyc/{id}', name: 'pro_kyc', methods: ['GET', 'POST'])]
    public function showProKyc($id, Request $request): Response
    {
        $user = $this->entityManager->getRepository(User::class)->find($id);

        if ($request->isMethod('POST')) {
            $status = $request->request->get('status');
            $message = $request->request->get('message');

            if ($status === 'approved') {
                $user->setVerificationStatus('approved');
                $user->setIsEnabled(true);

                $subject = $this->translator->trans('email.kyc_approved.title', [], null, $user->getLocale());

                $context = ['user' => $user];
                // Envoyer un email de confirmation
                $this->util->sender(
                    $this->util->getSetting()->getEmailSender(),
                    $subject,
                    '@emails/kyc_approved.html.twig',
                    [$user->getEmail()],
                    $context
                );

                $renderedView = $this->renderView('@emails/kyc_approved.html.twig', $context);

                $notif = new Notification();
                $notif->setSubject($subject);
                $notif->setContent($renderedView);
                $notif->setUser($user);
                $notif->setStatus("notsee");
                $this->entityManager->persist($notif);
                $this->entityManager->persist($user);
                $this->entityManager->flush();
                $this->addFlash('success', 'Compte validé avec succès');
            } elseif ($status === 'rejected') {
                $user->setVerificationStatus('rejected');
                $user->setIsEnabled(false);
                $subject = $this->translator->trans('email.kyc_rejected.title', [], null, $user->getLocale());
                $context = ['user' => $user, 'message' => $message];

                // Envoyer un email de rejet avec le message
                $this->util->sender(
                    $this->util->getSetting()->getEmailSender(),
                    $subject,
                    '@emails/kyc_rejected.html.twig',
                    [$user->getEmail()],
                    $context
                );
                $renderedView = $this->renderView('@emails/kyc_rejected.html.twig', $context);

                $notif = new Notification();
                $notif->setSubject($subject);
                $notif->setContent($renderedView);
                $notif->setUser($user);
                $notif->setStatus("notsee");
                $this->entityManager->persist($notif);
                $this->entityManager->persist($user);
                $this->entityManager->flush();
                $this->addFlash('success', 'Compte rejeté avec succès');
            }

            return $this->redirectToRoute('app_admin_clientLists');
        }

        return $this->render('@admin/user/pro_kyc.html.twig', [
            'user' => $user,
        ]);
    }
}
