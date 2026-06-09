<?php

namespace App\Controller;

use App\Repository\ActivityLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/logs')]
#[IsGranted('ROLE_ADMIN')]
class ActivityLogController extends AbstractController
{
    #[Route('', name: 'app_logs')]
    public function index(ActivityLogRepository $repo): Response
    {
        $logs = $repo->findBy([], ['createdAt' => 'DESC'], 100);

        return $this->render('activity_log/index.html.twig', [
            'logs' => $logs,
        ]);
    }
}