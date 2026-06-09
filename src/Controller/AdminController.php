<?php

namespace App\Controller;

use App\Repository\CleaningRequestRepository;
use App\Repository\PropertyRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    #[Route('', name: 'app_admin')]
    public function index(
        UserRepository $userRepo,
        PropertyRepository $propertyRepo,
        CleaningRequestRepository $requestRepo
    ): Response {
        $owners = $userRepo->findByRole('ROLE_OWNER');
        $cleaners = $userRepo->findByRole('ROLE_CLEANER');
        $properties = $propertyRepo->findAll();
        $requests = $requestRepo->findAll();

        $pending = array_filter($requests, fn($r) => $r->getStatus() === 'PENDING');
        $validated = array_filter($requests, fn($r) => $r->getStatus() === 'VALIDATED');
        $completed = array_filter($requests, fn($r) => $r->getStatus() === 'COMPLETED');
        $cancelled = array_filter($requests, fn($r) => $r->getStatus() === 'CANCELLED');

        return $this->render('admin/index.html.twig', [
            'totalOwners' => count($owners),
            'totalCleaners' => count($cleaners),
            'totalProperties' => count($properties),
            'totalRequests' => count($requests),
            'pendingRequests' => count($pending),
            'validatedRequests' => count($validated),
            'completedRequests' => count($completed),
            'cancelledRequests' => count($cancelled),
        ]);
    }
}