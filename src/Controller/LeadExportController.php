<?php

namespace App\Controller;

use App\Repository\LeadRepository;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/acquisition/export')]
#[IsGranted('ROLE_ADMIN')]
class LeadExportController extends AbstractController
{
    #[Route('/excel', name: 'app_lead_export_excel')]
    public function exportExcel(Request $request, LeadRepository $repo): StreamedResponse
    {
        $status = $request->query->get('status');
        $city = $request->query->get('city');
        $categoryId = $request->query->get('category');
        $scoreMin = $request->query->get('score_min');

        $query = $repo->findWithFiltersQuery($status, $city, $categoryId, $scoreMin, null);
        $leads = $query->getResult();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Prospects');

        $headers = ['Entreprise', 'Ville', 'Catégorie', 'Score', 'Statut', 'Téléphone', 'Email', 'Site web', 'Note Google', 'Avis Google', 'Créé le'];
        $sheet->fromArray($headers, null, 'A1');

       $sheet->getStyle('A1:K1')->getFont()->setBold(true);

        $row = 2;
        foreach ($leads as $lead) {
            $sheet->fromArray([
                $lead->getCompanyName(),
                $lead->getCity(),
                $lead->getCategory()?->getName() ?? '—',
                $lead->getScore(),
                $lead->getStatus(),
                $lead->getPhone() ?? '—',
                $lead->getEmail() ?? '—',
                $lead->getWebsite() ?? '—',
                $lead->getGoogleRating() ?? '—',
                $lead->getGoogleReviews() ?? '—',
                $lead->getCreatedAt()->format('d/m/Y'),
            ], null, 'A' . $row);
            $row++;
        }

        foreach (range('A', 'K') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);

        $response = new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="prospects_' . date('Y-m-d') . '.xlsx"');

        return $response;
    }
}