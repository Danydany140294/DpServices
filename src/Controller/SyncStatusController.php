<?php

namespace App\Controller;

use App\Repository\SyncLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/sync-status')]
#[IsGranted('ROLE_ADMIN')]
class SyncStatusController extends AbstractController
{
    #[Route('', name: 'app_sync_status')]
    public function index(SyncLogRepository $syncLogRepository): Response
    {
        $lastSuccessful = $syncLogRepository->findLastSuccessful();
        $errorsLast24h = $syncLogRepository->countErrorsSince(new \DateTime('-24 hours'));
        $recentLogs = $syncLogRepository->findRecent(50);

        return $this->render('sync_status/index.html.twig', [
            'lastSuccessful' => $lastSuccessful,
            'errorsLast24h' => $errorsLast24h,
            'recentLogs' => $recentLogs,
        ]);
    }
}