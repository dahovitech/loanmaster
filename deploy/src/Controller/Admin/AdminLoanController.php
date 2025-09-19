<?php

namespace App\Controller\Admin;

use App\Entity\Loan;
use App\Entity\User;
use App\Service\Util;
use App\Entity\Notification;
use App\Form\LoanPayFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/admin/loan', name: 'admin_loan_')]
class AdminLoanController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TranslatorInterface $translator,
        private Util $util,
        private UrlGeneratorInterface $urlGenerator
    ) {}

    #[Route('/list', name: 'list')]
    public function list(): Response
    {
        $loans = $this->entityManager->getRepository(Loan::class)
        ->createQueryBuilder('l')
        ->orderBy('l.id', 'DESC') // Trie par l'ID en ordre décroissant
        ->getQuery()
        ->getResult();
        
        return $this->render('@admin/user/loan/list.html.twig', [
            'loans' => $loans,
        ]);
    }

    #[Route(path: '/bankinfo', name: 'bankinfo')]
    public function bankinfo(Request $request): Response
    {
        $loanNumber = $request->query->get('loanNumber');
        $type = $request->query->get('type');

        $email = $request->query->get("email");
        $user = $this->entityManager->getRepository(User::class)->findOneBy(["email" => $email]);

        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }

        $loan = $this->entityManager->getRepository(Loan::class)->findOneBy(["loanNumber" => $loanNumber]);
        if (!$loan) {
            throw $this->createNotFoundException('Loan not found');
        }

        $loan->setStatus("success");
        $this->entityManager->flush();


        $subject = $this->translator->trans("email.subject.bankinfo", [], null, $user->getLocale());

        $this->addFlash(
            'success',
            $this->translator->trans("Info envoyé avec succèss")
        );

        $context = [
            'user' => $user,
            'loanNumber' => $loanNumber,
            'loan' => $loan,
            'locale' => $user->getLocale(),
            'subject' => $subject,
        ];

        $this->util->sender(
            $this->util->getSetting()->getEmailSender(),
            $subject,
            '@emails/bankinfo.html.twig',
            [$user->getEmail()],
            $context
        );

        $this->sendNotification($user, $subject, '@emails/bankinfo.html.twig', $context);

        return $this->redirectToRoute("admin_loan_list");
    }



    #[Route('/{id}', name: 'show')]
    public function show($id): Response
    {
        $loan = $this->entityManager->getRepository(Loan::class)->find($id);
        if (!$loan) {
            throw $this->createNotFoundException('Loan not found');
        }
        $template_name = "";
        if ($loan->getUser()->getAccountType() == "individual") {
            $template_name = "show.html.twig";
        }
        if ($loan->getUser()->getAccountType() == "professional") {
            $template_name = "show_pro.html.twig";
        }
        return $this->render('@admin/user/loan/' . $template_name, [
            'loan' => $loan,
        ]);
    }

    #[Route('/payinfo/{id}', name: 'pay_info')]
    public function pay_info($id, Request $request): Response
    {
        $loan = $this->entityManager->getRepository(Loan::class)->find($id);
        if (!$loan) {
            throw $this->createNotFoundException('Loan not found');
        }

        $form = $this->createForm(LoanPayFormType::class, $loan);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $loan->setPayStatus("notorder");
            $user = $loan->getUser();
            // Send email to client
            $context = [
                'user' => $user,
                'loan' => $loan,
                'url' => $this->urlGenerator->generate("app_user_payment", ["loanNumber" => $loan->getLoanNumber(),  'type' => 'folder'], 0),
                'locale' => $user->getLocale()
            ];

            $this->addFlash(
                'success',
                $this->translator->trans("Info paiement ajouter avec succèss")
            );

            $subject = $this->translator->trans("email.subject.loanOrderPay", [], null, $user->getLocale());

            $this->util->sender(
                $this->util->getSetting()->getEmailSender(),
                $subject,
                '@emails/loan_order_pay.html.twig',
                [$user->getEmail()],
                $context
            );

            $this->sendNotification($user, $subject, '@emails/loan_order_pay.html.twig', $context);

            $this->entityManager->flush();

            return $this->redirectToRoute("admin_loan_list");
        }


        return $this->render('@admin/user/loan/payinfo.html.twig', [
            'loan' => $loan,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/payinfoupdate/{id}', name: 'pay_info_update')]
    public function pay_info_update($id, Request $request): Response
    {
        $loan = $this->entityManager->getRepository(Loan::class)->find($id);
        if (!$loan) {
            throw $this->createNotFoundException('Loan not found');
        }

        $form = $this->createForm(LoanPayFormType::class, $loan);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $this->entityManager->flush();
            $this->addFlash(
                'success',
                $this->translator->trans("Info paiement mise à jours avec succèss")
            );
            return $this->redirectToRoute("admin_loan_list");
        }


        return $this->render('@admin/user/loan/payinfo.html.twig', [
            'loan' => $loan,
            'form' => $form->createView(),
        ]);
    }

    private function sendNotification(User $user, string $subject, string $template, array $context): void
    {
        $renderedView = $this->renderView($template, $context);

        $notif = new Notification();
        $notif->setSubject($subject);
        $notif->setContent($renderedView);
        $notif->setUser($user);
        $notif->setStatus("notsee");
        $this->entityManager->persist($notif);
        $this->entityManager->flush();
    }

    #[Route(path: '/payment/verify-not-received', name: 'payment_verify_not_received')]
    public function verifyPaymentNotReceived(Request $request): Response
    {
        $loanNumber = $request->query->get('loanNumber');

        $type = $request->query->get('type');
        $email = $request->query->get("email");
        $user = $this->entityManager->getRepository(User::class)->findOneBy(["email" => $email]);

        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }


        $loan = $this->entityManager->getRepository(Loan::class)->findOneBy(["loanNumber" => $loanNumber]);
        if (!$loan) {
            throw $this->createNotFoundException('Loan not found');
        }
        if ($type == "folder") {
            $loan->setPayStatus("notreceived");
        }
        if ($type == "contract") {
            $loan->setPayContractStatus("notreceived");
        }

        $this->entityManager->persist($loan);
        $this->entityManager->flush();


        $this->addFlash(
            'success',
            $this->translator->trans("Commande vérifier avec succèss")
        );

        $context = [
            'user' => $user,
            'loanNumber' => $loanNumber,
            'loan' => $loan,
            'locale' => $user->getLocale()
        ];

        $subject = $this->translator->trans("email.subject.payVerifNotreceived", [], null, $user->getLocale());
        $this->util->sender(
            $this->util->getSetting()->getEmailSender(),
            $subject,
            '@emails/payment_verif_notreceived.html.twig',
            [$user->getEmail()],
            $context
        );

        $this->sendNotification($user, $subject, '@emails/payment_verif_notreceived.html.twig', $context);

        return $this->redirectToRoute("admin_loan_list");
    }

    #[Route(path: '/payment/reminder', name: 'payment_reminder')]
    public function paymentReminder(Request $request): Response
    {
        $loanNumber = $request->query->get('loanNumber');
        $type = $request->query->get('type');

        $email = $request->query->get("email");
        $user = $this->entityManager->getRepository(User::class)->findOneBy(["email" => $email]);

        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }

        $loan = null;
        $loan = $this->entityManager->getRepository(Loan::class)->findOneBy(["loanNumber" => $loanNumber]);
        if (!$loan) {
            throw $this->createNotFoundException('Loan not found');
        }


        $context = [
            'user' => $user,
            'loan' => $loan,
            'url' => $this->urlGenerator->generate("app_user_payment", ["loanNumber" => $loanNumber, 'type' => $type], 0),
            'locale' => $user->getLocale()
        ];

        $this->addFlash(
            'success',
            $this->translator->trans("Commande rappelé avec succèss")
        );

        $subject = $this->translator->trans("email.subject.loanReminder", [], null, $user->getLocale());

        $this->util->sender(
            $this->util->getSetting()->getEmailSender(),
            $subject,
            '@emails/payment_reminder.html.twig',
            [$user->getEmail()],
            $context
        );

        $this->sendNotification($user, $subject, '@emails/payment_reminder.html.twig', $context);

        return $this->redirectToRoute("admin_loan_list");
    }

    #[Route(path: '/payment/verify-success', name: 'payment_verify_success')]
    public function verifyPaymentSuccess(Request $request): Response
    {
        $loanNumber = $request->query->get('loanNumber');
        $type = $request->query->get('type');

        $email = $request->query->get("email");
        $user = $this->entityManager->getRepository(User::class)->findOneBy(["email" => $email]);

        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }

        $loan = $this->entityManager->getRepository(Loan::class)->findOneBy(["loanNumber" => $loanNumber]);
        if (!$loan) {
            throw $this->createNotFoundException('Loan not found');
        }

        if ($type == "folder") {
            $loan->setPayStatus("success");
        }
        if ($type == "contract") {
            $loan->setPayContractStatus("success");
        }
        $this->entityManager->persist($loan);
        $this->entityManager->flush();


        $this->addFlash(
            'success',
            $this->translator->trans("Commande vérifier avec succèss")
        );

        $context = [
            'user' => $user,
            'loanNumber' => $loanNumber,
            'loan' => $loan,
            'locale' => $user->getLocale()
        ];

        $subject = $this->translator->trans("email.subject.payVerifSuccess", [], null, $user->getLocale());
        $this->util->sender(
            $this->util->getSetting()->getEmailSender(),
            $subject,
            '@emails/payment_verif_success.html.twig',
            [$user->getEmail()],
            $context
        );

        $this->sendNotification($user, $subject, '@emails/payment_verif_success.html.twig', $context);

        return $this->redirectToRoute("admin_loan_list");
    }
}
