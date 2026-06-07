<?php

namespace App\Controller;

use App\Repository\CleaningRequestRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class CalendarController extends AbstractController
{
    #[Route('/calendar', name: 'app_calendar')]
    #[IsGranted('ROLE_USER')]
    public function index(UserRepository $userRepository): Response
    {
        $cleaners = $userRepository->findByRole('ROLE_CLEANER');
        $owners = $userRepository->findByRole('ROLE_OWNER');

        return $this->render('calendar/index.html.twig', [
            'cleaners' => $cleaners,
            'owners' => $owners,
        ]);
    }

    #[Route('/api/calendar/events', name: 'app_calendar_events')]
#[IsGranted('ROLE_USER')]
public function events(CleaningRequestRepository $repo, Request $request): JsonResponse
{
    $cleanerId = $request->query->get('cleaner');
    $ownerId = $request->query->get('owner');

    $requests = $repo->findAll();
    $events = [];

    foreach ($requests as $req) {
        // Vue propriétaire : uniquement ses logements
        if ($this->isGranted('ROLE_OWNER') && !$this->isGranted('ROLE_ADMIN')) {
            if ($req->getProperty()->getOwner()->getId() !== $this->getUser()->getId()) {
                continue;
            }
        }

        // Vue cleaner : uniquement ses missions
        if ($this->isGranted('ROLE_CLEANER') && !$this->isGranted('ROLE_ADMIN')) {
            if (!$req->getAssignedCleaner() || $req->getAssignedCleaner()->getId() !== $this->getUser()->getId()) {
                continue;
            }
        }

        // Filtres admin
        if ($cleanerId && (!$req->getAssignedCleaner() || $req->getAssignedCleaner()->getId() != $cleanerId)) {
            continue;
        }
        if ($ownerId && $req->getProperty()->getOwner()->getId() != $ownerId) {
            continue;
        }

        $events[] = [
            'id' => $req->getId(),
            'title' => $req->getProperty()->getName(),
            'start' => $req->getScheduledDate()->format('Y-m-d') . 'T' . $req->getScheduledTime()->format('H:i:s'),
            'backgroundColor' => $req->getProperty()->getColor(),
            'borderColor' => $req->getProperty()->getColor(),
            'extendedProps' => [
                'status' => $req->getStatus(),
                'service' => $req->getService()->getName(),
                'cleaner' => $req->getAssignedCleaner() ? $req->getAssignedCleaner()->getFirstname() . ' ' . $req->getAssignedCleaner()->getLastname() : 'Non assigné',
            ],
        ];
    }

    return $this->json($events);
}
}