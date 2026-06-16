<?php

namespace App\Service;

use App\Entity\Devis;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

class DevisPdfService
{
    public function __construct(private Environment $twig) {}

    public function generer(Devis $devis): string
    {
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);

        $html = $this->twig->render('devis/pdf.html.twig', [
            'devis' => $devis,
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}