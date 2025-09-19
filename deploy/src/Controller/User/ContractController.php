<?php

namespace App\Controller\User;

use Dompdf\Dompdf;
use Dompdf\Options;
use App\Entity\Loan;
use App\Entity\Media;
use App\Service\Util;
use App\Entity\Notification;
use App\Repository\LoanRepository;
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
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[Route(
    path: '/{_locale}/account/contract',
    name: 'app_user_contract_'
)]
#[IsGranted('ROLE_USER')]
class ContractController extends AbstractController
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

    #[Route('/generate', name: 'generate')]
    public function downloadPdf(Request $request): Response
    {
        $loanNumber = $request->query->get('loanNumber');
        $type = $request->query->get('type');
      
        $email = $request->query->get("email");
        $user = $this->getUser();

        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }

        $loan = null;
            $loan = $this->entityManager->getRepository(Loan::class)->findOneBy(["loanNumber" => $loanNumber]);
            if (!$loan) {
                throw $this->createNotFoundException('Loan not found');
            }
        

        $options = new Options();
        $options->set('defaultFont', 'Roboto');
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);

        $template_name = "";
        if ($loan->getUser()->getAccountType() == "individual") {
            $template_name = "contract.html.twig";
        }
        if ($loan->getUser()->getAccountType() == "professional") {
            $template_name = "contract_pro.html.twig";
        }
        $htmlContent = $this->renderView('user/loan/' . $template_name, [
            'loan' => $loan,
            'bank' => $loan->getBank(),
            'user' => $loan->getUser(),
        ]);

        $dompdf->loadHtml($htmlContent);

        $dompdf->setPaper('A4', 'portrait');

        $dompdf->render();

        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont("Roboto");
        $canvas->page_text(520, 780, "Page {PAGE_NUM} sur {PAGE_COUNT}", $font, 10, array(0, 0, 0));

        $dompdf->stream("contrat.pdf", ["Attachment" => true]);

        return new Response();
    }

    #[Route('/preview', name: 'preview')]
    public function preview(Request $request): Response
    {
        $loanNumber = $request->query->get('loanNumber');
        $type = $request->query->get('type');
      
        $email = $request->query->get("email");
        $user = $this->getUser();

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

        return $this->render('user/loan/' . $template_name, [
            'loan' => $loan,
            'bank' => $loan->getBank(),
            'user' => $loan->getUser(),
        ]);
    }


    #[Route('/signature', name: 'signature')]
    public function signature(Request $request): Response
    {
        $type = $request->query->get('type');
      
        $loanNumber = $request->query->get('loanNumber');
        $loan = null;


        $loan = $this->loanRepository->findOneBy(["loanNumber" => $loanNumber]);


        return $this->render('user/loan/signature.html.twig', [
            "loan" => $loan,
            'type' => $type,
            "loanNumber" => $loanNumber,
        ]);
    }



    #[Route('/signaturesave', name: 'signature_save')]
    public function signaturesave(Request $request): Response
    {
        $type = $request->query->get('type');
      
        $file = $request->files->get('file');
        $loanNumber = $request->query->get('loanNumber');
        $loan = null;
        $context = [];

        $loan = $this->loanRepository->findOneBy(["loanNumber" => $loanNumber]);
        if (!$loan) {
            $this->addFlash('error', $this->translator->trans("flash.loan.not_found"));
            return $this->redirectToRoute("app_user_contract_signature", ["loanNumber" => $loanNumber, 'type' => $type]);
        }

        if (!$file) {
            $this->addFlash('error', $this->translator->trans("flash.file.not_uploaded"));
            return $this->redirectToRoute("app_user_contract_signature", ["loanNumber" => $loanNumber, 'type' => $type]);
        }

        // Validate file type and size
        $allowedMimeTypes = ['application/pdf', 'image/jpeg', 'image/png'];
        $maxFileSize = 5 * 1024 * 1024; // 5MB

        if (!in_array($file->getMimeType(), $allowedMimeTypes)) {
            $this->addFlash('error', $this->translator->trans("flash.file.invalid_type"));
            return $this->redirectToRoute("app_user_contract_signature", ["loanNumber" => $loanNumber, 'type' => $type]);
        }

        if ($file->getSize() > $maxFileSize) {
            $this->addFlash('error', $this->translator->trans("flash.file.too_large"));
            return $this->redirectToRoute("app_user_contract_signature", ["loanNumber" => $loanNumber, 'type' => $type]);
        }

        $media = new Media();
        $media->setFile($file);
        $loan->setContractSignFile($media);
        $loan->setContractStatus("submit");
        $this->entityManager->persist($loan);
        $this->entityManager->flush();

        $subject = $this->translator->trans("email.subject.contrat.signature", [], 'messages', $loan->getUser()->getLocale());

        $context = [
            'user' => $this->getUser(),
            'loanNumber' => $loanNumber,
            'loan' => $loan,
            'subject' => $subject
        ];

        $this->addFlash(
            'success',
            $this->translator->trans("flash.contrat.signature")
        );

        $this->util->sender(
            $this->util->getSetting()->getEmailSender(),
            $subject,
            '@emails/contract_signature.html.twig',
            [$this->getUser()->getEmail()],
            $context
        );

        $renderedView = $this->renderView('@emails/contract_signature.html.twig', $context);

        $this->util->sender(
            $this->util->getSetting()->getEmailSender(),
            "Signature de contrat",
            '@emails/admin_filSignature.html.twig',
            [$this->util->getSetting()->getEmail()],
            ["fil" => $loan->getContractSignFile()->getWebPath(), 'loanNumber' => $loanNumber,]
        );

        $notif = new Notification();
        $notif->setSubject($subject);
        $notif->setContent($renderedView);
        $notif->setUser($this->getUser());
        $notif->setStatus("notsee");
        $this->entityManager->persist($notif);
        $this->entityManager->flush();

        $this->addFlash('success', $this->translator->trans("flash.contrat.signature_success"));

        return $this->json(["urlList" => $this->urlGenerator->generate("app_user_loan_list", [], 0),]);
    }
}
