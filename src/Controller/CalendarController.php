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
use Doctrine\ORM\EntityManagerInterface;

class CalendarController extends AbstractController
{
    #[Route('/calendar', name: 'app_calendar')]
#[IsGranted('ROLE_USER')]
public function index(UserRepository $userRepository): Response
{
    $cleaners = $userRepository->findByRole('ROLE_CLEANER');
    $owners = $userRepository->findByRole('ROLE_OWNER');

    // Template différent pour la FdM
    if ($this->isGranted('ROLE_CLEANER') && !$this->isGranted('ROLE_ADMIN')) {
        return $this->render('calendar/cleaner.html.twig');
    }

    return $this->render('calendar/index.html.twig', [
        'cleaners' => $cleaners,
        'owners' => $owners,
    ]);
}

    #[Route('/api/calendar/events', name: 'app_calendar_events')]
#[IsGranted('ROLE_USER')]
public function events(CleaningRequestRepository $repo, \App\Repository\LeadRepository $leadRepo, Request $request): JsonResponse
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
            'id' => 'cr_' . $req->getId(),
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

    // J59 — Rappels de prospection (uniquement visibles par l'admin)
    if ($this->isGranted('ROLE_ADMIN')) {
        $leadsToFollow = $leadRepo->findBy(['status' => 'TO_FOLLOW_UP']);
        foreach ($leadsToFollow as $lead) {
            if (!$lead->getNextFollowUp()) {
                continue;
            }
            $events[] = [
                'id' => 'lead_' . $lead->getId(),
                'title' => '📞 Relancer : ' . $lead->getCompanyName(),
                'start' => $lead->getNextFollowUp()->format('Y-m-d'),
                'backgroundColor' => '#7c3aed',
                'borderColor' => '#7c3aed',
                'url' => $this->generateUrl('app_lead_show', ['id' => $lead->getId()]),
                'extendedProps' => [
                    'type' => 'prospection',
                    'city' => $lead->getCity(),
                ],
            ];
        }
    }

    return $this->json($events);
}

#[Route('/api/calendar/events/{id}/move', name: 'app_calendar_event_move', methods: ['POST'])]
#[IsGranted('ROLE_ADMIN')]
public function moveEvent(
    int $id,
    Request $request,
    CleaningRequestRepository $repo,
    EntityManagerInterface $em,
    \App\Service\GoogleSyncService $googleSyncService
): JsonResponse {
    $cleaningRequest = $repo->find($id);

    if ($cleaningRequest === null) {
        return $this->json(['success' => false, 'error' => 'Mission introuvable'], 404);
    }

    $data = json_decode($request->getContent(), true);
    $newStart = $data['start'] ?? null;

    if ($newStart === null) {
        return $this->json(['success' => false, 'error' => 'Date manquante'], 400);
    }

    try {
        $dateTime = new \DateTime($newStart);
    } catch (\Exception $e) {
        return $this->json(['success' => false, 'error' => 'Date invalide'], 400);
    }

    $cleaningRequest->setScheduledDate(clone $dateTime);
    $cleaningRequest->setScheduledTime(clone $dateTime);
    $em->flush();

    try {
        $googleSyncService->pushUpdate($cleaningRequest);
    } catch (\Throwable $e) {
        // pushUpdate a déjà loggé l'erreur dans SyncLog ; on informe juste le front
        return $this->json(['success' => true, 'warning' => 'Mission déplacée, mais la synchro Google a échoué']);
    }

    return $this->json(['success' => true]);
}
}