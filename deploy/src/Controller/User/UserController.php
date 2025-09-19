<?php

namespace App\Controller\User;

use App\Entity\Media;
use App\Service\Util;
use App\Entity\Notification;
use App\Form\ChangePasswordType;
use App\Repository\LoanRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\NotificationRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[Route(
    path: '/{_locale}/account',
    name: 'app_user_'
)]
#[IsGranted('ROLE_USER')]
class UserController extends AbstractController
{

    public function __construct(
        private TranslatorInterface $translator,
        private Util $util,
        private UrlGeneratorInterface $urlGenerator,
        private NotificationRepository $notificationRepository,
        private KernelInterface $kernel,
        private ParameterBagInterface $parameterBag,
        private EntityManagerInterface $entityManager,
        private LoanRepository $loanRepository

    ) {}

    #[Route(path: '/', name: 'dashboard')]
    public function dashboard(): Response
    {

        // dd($this->notificationRepository->findByUser($this->getUser()));
        return $this->render('@admin/dashboard.html.twig', [
            'notifications' => $this->notificationRepository->findByUser($this->getUser())
        ]);
    }

    #[Route(path: '/change_password', name: 'change_password')]
    public function changePassword(Request $request, UserRepository $userRepository, UserPasswordHasherInterface $userPasswordHasher): Response
    {
        $user = $this->getUser();
        $user = $userRepository->find($user->getId());

        $form = $this->createForm(ChangePasswordType::class, $user);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if (!$userPasswordHasher->isPasswordValid($user, $form->get('currentPassword')->getData())) {
                $this->addFlash('error', $this->translator->trans("flash.error_currentPassword"));
            } else {
                $user->setPassword(
                    $userPasswordHasher->hashPassword(
                        $user,
                        $form->get('plainPassword')->getData()
                    )
                );

                $this->entityManager->persist($user);
                $this->entityManager->flush();
                $this->addFlash('success', $this->translator->trans("flash.change_password"));
            }
        }
        return $this->render('@admin/change_password.html.twig', [
            'form' => $form->createView(),
        ]);
    }


    #[Route('/payment', name: 'payment')]
    public function payment(Request $request): Response
    {
        $type = $request->query->get('type');
      
        $loanNumber = $request->query->get('loanNumber');
        $loan = null;

        $loan = $this->loanRepository->findOneBy(["loanNumber" => $loanNumber]);


        return $this->render('@admin/payment/payment.html.twig', [
            "loan" => $loan,
            'type' => $type,
            "loanNumber" => $loanNumber,
        ]);
    }

    #[Route('/pay_file', name: 'pay_file')]
    public function payFile(Request $request): Response
    {
        $type = $request->query->get('type');
      
        $file = $request->files->get('file');
        $loanNumber = $request->query->get('loanNumber');
        $loan = null;
        $context = [];

        $loan = $this->loanRepository->findOneBy(["loanNumber" => $loanNumber]);
        if (!$loan) {
            $this->addFlash('error', $this->translator->trans("flash.loan.not_found"));
            return $this->redirectToRoute("app_user_payment", ["loanNumber" => $loanNumber,   'type' => $type]);
        }

        if (!$file) {
            $this->addFlash('error', $this->translator->trans("flash.file.not_uploaded"));
            return $this->redirectToRoute("app_user_payment", ["loanNumber" => $loanNumber,  'type' => $type]);
        }


        // Validate file type and size
        $allowedMimeTypes = ['application/pdf', 'image/jpeg', 'image/png'];
        $maxFileSize = 5 * 1024 * 1024; // 5MB

        if (!in_array($file->getMimeType(), $allowedMimeTypes)) {
            $this->addFlash('error', $this->translator->trans("flash.file.invalid_type"));
            return $this->redirectToRoute("app_user_payment", ["loanNumber" => $loanNumber,  'type' => $type]);
        }

        if ($file->getSize() > $maxFileSize) {
            $this->addFlash('error', $this->translator->trans("flash.file.too_large"));
            return $this->redirectToRoute("app_user_payment", ["loanNumber" => $loanNumber,  'type' => $type]);
        }

        $media = new Media();
        $media->setFile($file);
        $loan->setPayFile($media);

        if ($type == "folder") {
            $loan->setPayStatus("loanpay");
        }
        if ($type == "contract") {
            $loan->setPayContractStatus("loanpay");
        }
        $this->entityManager->persist($loan);
        $this->entityManager->flush();

        $subject = $this->translator->trans("email.subject.loan.pay", [], 'messages', $loan->getUser()->getLocale());

        $context = [
            'user' => $this->getUser(),
            'loanNumber' => $loanNumber,
            'loan' => $loan,
            'subject' => $subject
        ];
        $this->addFlash(
            'success',
            $this->translator->trans("flash.loan.pay")
        );


        $this->util->sender(
            $this->util->getSetting()->getEmailSender(),
            $subject,
            '@emails/loan_pay.html.twig',
            [$this->getUser()->getEmail()],
            $context
        );

        $this->util->sender(
            $this->util->getSetting()->getEmailSender(),
            "Justificatif de paiement",
            '@emails/admin_filpay.html.twig',
            [$this->util->getSetting()->getEmail()],
            ["fil" => $loan->getPayFile()->getWebPath()]
        );

        $renderedView = $this->renderView('@emails/loan_pay.html.twig', $context);

        $notif = new Notification();
        $notif->setSubject($subject);
        $notif->setContent($renderedView);
        $notif->setUser($this->getUser());
        $notif->setStatus("notsee");
        $this->entityManager->persist($notif);
        $this->entityManager->flush();
        $this->addFlash('success', $this->translator->trans("flash.loan.pay_success"));

        return $this->redirectToRoute("app_user_payment", ["loanNumber" => $loanNumber,  'type' => $type]);
    }
}
