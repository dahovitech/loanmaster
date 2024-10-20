<?php

namespace App\Controller\User;

use App\Entity\Loan;
use App\Service\Util;
use App\Form\LoanFormType;
use App\Entity\Notification;
use App\Form\LoanProFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[Route('/{_locale}/account/loan', name: 'app_user_loan_')]
#[IsGranted('ROLE_USER')]
class LoanController extends AbstractController
{
    private Util $util;
    private TranslatorInterface $translator;
    private EntityManagerInterface $entityManager;

    public function __construct(Util $util, TranslatorInterface $translator, EntityManagerInterface $entityManager)
    {
        $this->util = $util;
        $this->translator = $translator;
        $this->entityManager = $entityManager;
    }

    #[Route('/loan/list', name: 'list')]
    public function list(): Response
    {
        $loans = $this->entityManager->getRepository(Loan::class)->findBy(['user' => $this->getUser()]);

        return $this->render('user/loan/list.html.twig', [
            'loans' => $loans,
        ]);
    }

    #[Route('/submit', name: 'submit')]
    public function submit(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw new AccessDeniedException('User not logged in.');
        }

        $loan = new Loan();
        $form = null;
        if ($user->getAccountType() == "individual") {
            $form = $this->createForm(LoanFormType::class, $loan);
        }
        if ($user->getAccountType() == "professional") {
            $form = $this->createForm(LoanProFormType::class, $loan);
        }
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $setting = $this->util->getSetting();
            $loan->setPayStatus("pending");
            $loan->setContractStatus("");
            $loan->setPayContractStatus("");

            // Save the loan entity
            $loan->setUser($user);

            // Send email to admin
            $adminEmail = $setting->getEmail();
            $adminSubject = $this->translator->trans('email.admin.loan_submitted.subject');
            $adminBody = $this->translator->trans('email.admin.loan_submitted.body', ['%firstname%' => $user->getFirstname(), '%lastname%' => $user->getLastname()]);

            // try {
            $this->util->sender(
                $setting->getEmailSender(),
                $adminSubject,
                '@emails/admin.html.twig',
                [$adminEmail],
                ['body' => $adminBody, 'user' => $user, "type_demande" => $loan->getLoanType()]
            );
            // } catch (\Exception $e) {
            //     // Log the exception
            //     $this->addFlash('error', $this->translator->trans('email.admin.loan_submitted.error'));
            // }

            // Send email to client
            $clientSubject = $this->translator->trans('email.client.loan_submitted.subject');
            $clientBody = $this->translator->trans('email.client.loan_submitted.body');

            //  try {
            $this->util->sender(
                $setting->getEmailSender(),
                $clientSubject,
                '@emails/loan_submission.html.twig',
                [$user->getEmail()],
                ['body' => $clientBody, 'loan' => $loan, 'user' => $user]
            );

            $renderedView = $this->renderView('@notifs/notif.html.twig',   [
                'subject' => $clientSubject,
                'body' => $clientBody
            ]);

            $notif = new Notification();
            $notif->setSubject($clientSubject);
            $notif->setContent($renderedView);
            $notif->setUser($this->getUser());
            $notif->setStatus("notsee");
            $this->entityManager->persist($notif);
            // } catch (\Exception $e) {
            //     // Log the exception
            //     $this->addFlash('error', $this->translator->trans('email.client.loan_submitted.error'));
            // }

            $this->entityManager->persist($user);
            $this->entityManager->persist($loan);
            $this->entityManager->flush();

            return $this->redirectToRoute("app_user_loan_list");
        }

        $template_name = "";

        if ($user->getAccountType() == "individual") {
            $template_name = "submit.html.twig";
        }
        if ($user->getAccountType() == "professional") {
            $template_name = "submit_pro.html.twig";
        }

        return $this->render('user/loan/' . $template_name, [
            'form' => $form->createView(),
        ]);
    }



    #[Route('/{id}/edit', name: 'edit')]
    public function edit(Request $request, Loan $loan): Response
    {
        $form = $this->createForm(LoanFormType::class, $loan);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            // Add a flash message
            $this->addFlash('success', $this->translator->trans('loan.edit.success'));

            // Redirect to the loan list page
            return $this->redirectToRoute('app_user_loan_list');
        }

        return $this->render('user/loan/edit.html.twig', [
            'form' => $form->createView(),
            'loan' => $loan,
        ]);
    }

    #[Route('/{id}', name: 'show')]
    public function show($id): Response
    {
        $loan = $this->entityManager->getRepository(Loan::class)->find($id);
        if (!$loan) {
            throw $this->createNotFoundException('Loan not found');
        }

        return $this->render('user/loan/show.html.twig', [
            'loan' => $loan,
        ]);
    }
}
