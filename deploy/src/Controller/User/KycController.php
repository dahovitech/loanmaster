<?php

namespace App\Controller\User;

use App\Entity\User;
use App\Service\Util;
use App\Entity\Notification;
use App\Form\UserKycFormType;
use App\Form\UserProKycFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route(
    path: '/{_locale}/account',
    name: 'app_user_'
)]
#[IsGranted('ROLE_USER')]
class KycController extends AbstractController
{
    private $entityManager;
    private $translator;
    private $util;

    public function __construct(EntityManagerInterface $entityManager, TranslatorInterface $translator, Util $util)
    {
        $this->entityManager = $entityManager;
        $this->translator = $translator;
        $this->util = $util;
    }

    #[Route('/user/kyc', name: 'kyc')]
    public function submitKyc(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $setting = $this->util->getSetting();

        // Create the KYC form for individuals
        $form = $this->createForm(UserKycFormType::class, $user);
        $form->handleRequest($request);

        // Handle form submission
        if ($form->isSubmitted() && $form->isValid()) {
            // Update user verification status and save documents
            $user->setVerificationStatus('pending');
            $this->entityManager->persist($user);

            // Send email to admin
            $adminEmail = $setting->getEmail();
            $adminSubject = $this->translator->trans('email.admin.kyc_submitted.subject');
            $adminBody = $this->translator->trans('email.admin.kyc_submitted.body', ['%firstname%' => $user->getFirstname(), '%lastname%' => $user->getLastname()]);

            $this->util->sender(
                $setting->getEmailSender(),
                $adminSubject,
                '@emails/kyc_admin.html.twig',
                [$adminEmail],
                ['body' => $adminBody]
            );

            // Send email to client
            $clientSubject = $this->translator->trans('email.client.kyc_submitted.subject');
            $clientBody = $this->translator->trans('email.client.kyc_submitted.body');

            $this->util->sender(
                $setting->getEmailSender(),
                $clientSubject,
                '@emails/kyc_client.html.twig',
                [$user->getEmail()],
                ['body' => $clientBody]
            );

            $renderedView = $this->renderView('@notifs/notif.html.twig', [
                'subject' => $clientSubject,
                'body' => $clientBody
            ]);

            $notif = new Notification();
            $notif->setSubject($clientSubject);
            $notif->setContent($renderedView);
            $notif->setUser($this->getUser());
            $notif->setStatus("notsee");
            $this->entityManager->persist($notif);
            $this->entityManager->persist($user);

            // Add a success flash message
            $this->addFlash('success', $this->translator->trans('kyc.submittedSuccessfully'));

            $this->entityManager->flush();

            return $this->redirectToRoute('app_user_dashboard');
        }

        // Render the KYC form
        return $this->render('user/kyc_form.html.twig', [
            'form' => $form->createView(),
            'kycTitle' => $this->translator->trans('kyc.title'),
            'kycDescription' => $this->translator->trans('kyc.description'),
        ]);
    }

    #[Route('/user/pro-kyc', name: 'pro_kyc')]
    public function submitProKyc(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $setting = $this->util->getSetting();

        // Create the KYC form for professionals
        $form = $this->createForm(UserProKycFormType::class, $user);
        $form->handleRequest($request);

        // Handle form submission
        if ($form->isSubmitted() && $form->isValid()) {
            // Update user verification status and save documents
            $user->setVerificationStatus('pending');
            $this->entityManager->persist($user);

            // Send email to admin
            $adminEmail = $setting->getEmail();
            $adminSubject = $this->translator->trans('email.admin.kyc_submitted.subject');
            $adminBody = $this->translator->trans('email.admin.kyc_submitted.body', ['%firstname%' => $user->getFirstname(), '%lastname%' => $user->getLastname()]);

            $this->util->sender(
                $setting->getEmailSender(),
                $adminSubject,
                '@emails/kyc_admin.html.twig',
                [$adminEmail],
                ['body' => $adminBody]
            );

            // Send email to client
            $clientSubject = $this->translator->trans('email.client.kyc_submitted.subject');
            $clientBody = $this->translator->trans('email.client.kyc_submitted.body');

            $this->util->sender(
                $setting->getEmailSender(),
                $clientSubject,
                '@emails/kyc_client.html.twig',
                [$user->getEmail()],
                ['body' => $clientBody]
            );

            $renderedView = $this->renderView('@notifs/notif.html.twig', [
                'subject' => $clientSubject,
                'body' => $clientBody
            ]);

            $notif = new Notification();
            $notif->setSubject($clientSubject);
            $notif->setContent($renderedView);
            $notif->setUser($this->getUser());
            $notif->setStatus("notsee");
            $this->entityManager->persist($notif);
            $this->entityManager->persist($user);

            // Add a success flash message
            $this->addFlash('success', $this->translator->trans('kyc.submittedSuccessfully'));

            $this->entityManager->flush();

            return $this->redirectToRoute('app_user_dashboard');
        }

        // Render the KYC form for professionals
        return $this->render('user/pro_kyc_form.html.twig', [
            'form' => $form->createView(),
            'kycTitle' => $this->translator->trans('kyc.pro.title'),
            'kycDescription' => $this->translator->trans('kyc.pro.description'),
        ]);
    }
}