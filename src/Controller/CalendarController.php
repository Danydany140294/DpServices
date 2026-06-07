<?php

namespace App\Controller;

use App\Repository\CleaningRequestRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class CalendarController extends AbstractController
{
    #[Route('/calendar', name: 'app_calendar')]
    #[IsGranted('ROLE_USER')]
    public function index(): Response
    {
        return $this->render('calendar/index.html.twig');
    }

    #[Route('/api/calendar/events', name: 'app_calendar_events')]
    #[IsGranted('ROLE_USER')]
    public function events(CleaningRequestRepository $repo): JsonResponse
    {
        $requests = $repo->findAll();
        $events = [];

        foreach ($requests as $request) {
            $events[] = [
                'id' => $request->getId(),
                'title' => $request->getProperty()->getName(),
                'start' => $request->getScheduledDate()->format('Y-m-d') . 'T' . $request->getScheduledTime()->format('H:i:s'),
                'backgroundColor' => $request->getProperty()->getColor(),
                'borderColor' => $request->getProperty()->getColor(),
                'extendedProps' => [
                    'status' => $request->getStatus(),
                    'service' => $request->getService()->getName(),
                    'cleaner' => $request->getAssignedCleaner() ? $request->getAssignedCleaner()->getFirstname() . ' ' . $request->getAssignedCleaner()->getLastname() : 'Non assigné',
                ],
            ];
        }

        return $this->json($events);
    }
}