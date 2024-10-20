<?php

namespace App\Service;

use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

class PdfService
{
    private $twig;
    private $dompdf;

    public function __construct(Environment $twig)
    {
        $this->twig = $twig;

        // Configure Dompdf according to your needs
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

       
        $this->dompdf = new Dompdf($options);
        $this->dompdf->set_option('margin_top', '20mm');
        $this->dompdf->set_option('margin_bottom', '20mm');
    }

    public function generatePdf(string $template, array $data): string
    {
        // Render the Twig template to HTML
        $html = $this->twig->render($template, $data);

        // Load the HTML into Dompdf
        $this->dompdf->loadHtml($html);

        // Set the paper size and orientation
        $this->dompdf->setPaper('A4', 'portrait');

        // Render the PDF
        $this->dompdf->render();

        // Return the PDF as a string
        return $this->dompdf->output();
    }
}
