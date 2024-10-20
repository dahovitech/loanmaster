<?php

namespace App\Controller\Admin;

use Dompdf\Dompdf;
use Dompdf\Options;
use App\Entity\Loan;
use App\Entity\User;
use App\Service\Util;
use App\Entity\Notification;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/admin/loan/contract', name: 'admin_loan_contract_')]
class AdminContractController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TranslatorInterface $translator,
        private Util $util,
        private UrlGeneratorInterface $urlGenerator
    ) {}

    #[Route('/preview', name: 'preview')]
    public function preview(Request $request): Response
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


        $template_name = "";

        if ($loan->getUser()->getAccountType() == "individual") {
            $template_name = "contract.html.twig";
        }
        if ($loan->getUser()->getAccountType() == "professional") {
            $template_name = "contract_pro.html.twig";
        }

        return $this->render('@admin/user/loan/' . $template_name, [
            'loan' => $loan,
            'bank' => $loan->getBank(),
            'user' => $loan->getUser(),
        ]);
    }

    #[Route('/sendcontract', name: 'send')]
    public function sendcontract(Request $request): Response
    {
        $type = $request->query->get('type');

        $file = $request->files->get('file');
        $loanNumber = $request->query->get('loanNumber');
        $loan = null;
        $context = [];

        $loan = $this->entityManager->getRepository(Loan::class)->findOneBy(["loanNumber" => $loanNumber]);
        if (!$loan) {
            $this->addFlash('error', $this->translator->trans("flash.loan.not_found"));
            return $this->redirectToRoute("admin_loan_contract_list");
        }
        $loan->setContractStatus("pending");
        $loan->setStatus("loading");

        $subject = $this->translator->trans("email.subject.contract.send", [], 'messages', $loan->getUser()->getLocale());

        $context = [
            'user' => $this->getUser(),
            'loanNumber' => $loanNumber,
            'loan' => $loan,
            'subject' => $subject,
            "url_preview" => $this->urlGenerator->generate("app_user_contract_preview", ["loanNumber" => $loan->getLoanNumber(),  'type' => 'contract', 'email' => $loan->getUser()->getEmail()], 0),
            "url_signature" => $this->urlGenerator->generate("app_user_contract_signature", ["loanNumber" => $loan->getLoanNumber(),  'type' => 'contract', 'email' => $loan->getUser()->getEmail()], 0),
        ];
        $this->addFlash(
            'success',
            $this->translator->trans("flash.contrat.send")
        );


        $this->util->sender(
            $this->util->getSetting()->getEmailSender(),
            $subject,
            '@emails/send_contract.html.twig',
            [$loan->getUser()->getEmail()],
            $context
        );


        $renderedView = $this->renderView('@emails/send_contract.html.twig', $context);

        $notif = new Notification();
        $notif->setSubject($subject);
        $notif->setContent($renderedView);
        $notif->setUser($this->getUser());
        $notif->setStatus("notsee");
        $this->entityManager->persist($notif);
        $this->entityManager->flush();
        $this->addFlash('success', $this->translator->trans("Le contrat a été envoyé avec succès."));

        return $this->redirectToRoute("admin_loan_list");
    }


    #[Route(path: '/validesignature', name: 'valide_signature')]
    public function validesignature(Request $request): Response
    {
        $loanNumber = $request->query->get('loanNumber');

        $state = $request->query->get('state');
        $email = $request->query->get("email");
        $user = $this->entityManager->getRepository(User::class)->findOneBy(["email" => $email]);

        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }

        $loan = $this->entityManager->getRepository(Loan::class)->findOneBy(["loanNumber" => $loanNumber]);
        if (!$loan) {
            throw $this->createNotFoundException('Loan not found');
        }
        $loan->setContractStatus($state);

        if ($state == "signed") {
            $loan->setPayContractStatus("notorder");
        }

        $this->entityManager->persist($loan);
        $this->entityManager->flush();

        $this->addFlash(
            'success',
            $this->translator->trans("flash.status." . $state)
        );

        $context = [
            'user' => $user,
            'loanNumber' => $loanNumber,
            'loan' => $loan,
            'locale' => $user->getLocale(),
            "url_signature" => $this->urlGenerator->generate("app_user_contract_signature", ["loanNumber" => $loan->getLoanNumber(),  'type' => 'contract', 'email' => $loan->getUser()->getEmail()], 0),
            'url' => $this->urlGenerator->generate("app_user_payment", ["loanNumber" => $loanNumber,  'type' => "contract"], 0),

        ];

        $subject = $this->translator->trans("email.subject.contract." . $state, [], null, $user->getLocale());
        $this->util->sender(
            $this->util->getSetting()->getEmailSender(),
            $subject,
            '@emails/valideContract' . ucfirst($state) . '.html.twig',
            [$user->getEmail()],
            $context
        );

        $this->sendNotification($user, $subject, '@emails/valideContract' . ucfirst($state) . '.html.twig', $context);

        return $this->redirectToRoute("admin_loan_list");
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
}
