<?php

namespace App\Controller;

use App\Entity\Country;
use App\Service\PdfService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route(
    path: '/{_locale}',

)]
class PdfController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private KernelInterface $kernel,

    ) {}

    #[Route('/generate-pdf/country/{code}', name: 'generate_pdf')]
    public function generatePdf(PdfService $pdfService,$code): Response
    {
        $country=$this->entityManager->getRepository(Country::class)->findOneBy(["code" => $code, "isEnabled" => true]);
        //$imagePath = $this->kernel->getProjectDir() . '/public' . $country->getImage();
        // $imageData = base64_encode(file_get_contents($imagePath));
        // $imageMimeType = (new File($imagePath))->getMimeType();
        // Data to be passed to the PDF template
        $data = [
            'country' => $country,
            // 'imageData' => $imageData,
            // 'imageMimeType' => $imageMimeType,
        ];


        // Generate the PDF from a Twig template
        $pdfContent = $pdfService->generatePdf('@theme/pdf/template.html.twig', $data);

        // Return the PDF as a response
        return new Response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="document.pdf"',
        ]);
    }
}
