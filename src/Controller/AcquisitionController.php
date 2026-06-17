<?php

namespace App\Controller;

use App\Repository\LeadRepository;
use App\Repository\DevisRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/acquisition')]
#[IsGranted('ROLE_ADMIN')]
class AcquisitionController extends AbstractController
{
    #[Route('', name: 'app_acquisition')]
    public function index(): Response
    {
        return $this->render('acquisition/index.html.twig');
    }

    #[Route('/dashboard', name: 'app_acquisition_dashboard')]
    public function dashboard(LeadRepository $leadRepo): Response
    {
        $allLeads = $leadRepo->findAll();

        $total = count($allLeads);
        $clients = count(array_filter($allLeads, fn($l) => $l->getStatus() === 'CLIENT'));
        $qualified = count(array_filter($allLeads, fn($l) => $l->getScore() >= 50));
        $lost = count(array_filter($allLeads, fn($l) => $l->getStatus() === 'LOST'));

        $conversionRate = $total > 0 ? round(($clients / $total) * 100, 1) : 0;

        // J67 — CA potentiel : leads qualifiés (score >= 50, pas encore clients) × tarif moyen estimé (45€/prestation)
        $avgTarif = 45;
        $potentialLeads = count(array_filter($allLeads, fn($l) => $l->getScore() >= 50 && $l->getStatus() !== 'CLIENT' && $l->getStatus() !== 'LOST'));
        $potentialRevenue = $potentialLeads * $avgTarif;

        $byCategory = [];
        foreach ($allLeads as $lead) {
            $catName = $lead->getCategory()?->getName() ?? 'Sans catégorie';
            $byCategory[$catName] = ($byCategory[$catName] ?? 0) + 1;
        }

        $byStatus = [];
        foreach ($allLeads as $lead) {
            $byStatus[$lead->getStatus()] = ($byStatus[$lead->getStatus()] ?? 0) + 1;
        }

        return $this->render('acquisition/dashboard.html.twig', [
            'total' => $total,
            'clients' => $clients,
            'qualified' => $qualified,
            'lost' => $lost,
            'conversionRate' => $conversionRate,
            'potentialLeads' => $potentialLeads,
            'potentialRevenue' => $potentialRevenue,
            'byCategory' => $byCategory,
            'byStatus' => $byStatus,
        ]);
    }
}