<?php

namespace App\Controller;

use App\Entity\Lead;
use App\Repository\LeadCategoryRepository;
use App\Repository\LeadRepository;
use App\Service\ActivityLogService;
use App\Service\ScoringService;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/acquisition/import-file')]
#[IsGranted('ROLE_ADMIN')]
class LeadImportController extends AbstractController
{
    #[Route('', name: 'app_lead_import_file', methods: ['GET', 'POST'])]
    public function import(
        Request $request,
        EntityManagerInterface $em,
        LeadRepository $repo,
        LeadCategoryRepository $categoryRepo,
        ScoringService $scoring,
        ActivityLogService $logger
    ): Response {
        if ($request->isMethod('POST')) {
            $file = $request->files->get('file');

            if (!$file) {
                $this->addFlash('error', 'Aucun fichier sélectionné.');
                return $this->redirectToRoute('app_lead_import_file');
            }

            try {
                $spreadsheet = IOFactory::load($file->getPathname());
                $sheet = $spreadsheet->getActiveSheet();
                $rows = $sheet->toArray();

                // Première ligne = en-têtes
                $headers = array_map('strtolower', array_map('trim', $rows[0]));
                array_shift($rows);

                $existingNames = array_map(
                    fn(Lead $l) => strtolower(trim($l->getCompanyName())),
                    $repo->findAll()
                );

                $imported = 0;
                $skipped = 0;

                foreach ($rows as $row) {
                    $data = array_combine($headers, $row);

                    $name = trim($data['entreprise'] ?? $data['nom'] ?? $data['companyname'] ?? '');
                    if (!$name) continue;

                    $normalized = strtolower($name);
                    if (in_array($normalized, $existingNames)) {
                        $skipped++;
                        continue;
                    }

                    $lead = new Lead();
                    $lead->setCompanyName($name);
                    $lead->setCity(trim($data['ville'] ?? $data['city'] ?? ''));
                    $lead->setEmail(trim($data['email'] ?? '') ?: null);
                    $lead->setPhone(trim($data['telephone'] ?? $data['phone'] ?? '') ?: null);
                    $lead->setSource('Import Excel');

                    $categoryName = trim($data['categorie'] ?? $data['category'] ?? '');
                    if ($categoryName) {
                        $category = $categoryRepo->findOneBy(['name' => $categoryName]);
                        $lead->setCategory($category);
                    }

                    $lead->setScore($scoring->calculate($lead));

                    $em->persist($lead);
                    $existingNames[] = $normalized;
                    $imported++;
                }

                $em->flush();
                $logger->log('Import fichier', $imported . ' prospects importés, ' . $skipped . ' doublons ignorés');
                $this->addFlash('success', $imported . ' prospect(s) importé(s), ' . $skipped . ' doublon(s) ignoré(s).');
                return $this->redirectToRoute('app_leads');

            } catch (\Throwable $e) {
                $this->addFlash('error', 'Erreur lors de l\'import : ' . $e->getMessage());
            }
        }

        return $this->render('lead/import_file.html.twig');
    }
}