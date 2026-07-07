<?php

namespace App\Controller;

use App\Repository\CleaningRequestRepository;
use App\Repository\UserRepository;
use App\Repository\LeadRepository;
use App\Service\GoogleCalendarService;
use App\Service\GoogleSyncService;
use Doctrine\ORM\EntityManagerInterface;
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

        if ($this->isGranted('ROLE_CLEANER') && !$this->isGranted('ROLE_ADMIN')) {
    return $this->render('calendar/cleaner.html.twig', [
        'today' => new \DateTime('today'),
    ]);
}

        return $this->render('calendar/index.html.twig', [
            'cleaners' => $cleaners,
            'owners' => $owners,
        ]);
    }

    #[Route('/api/calendar/events', name: 'app_calendar_events')]
    #[IsGranted('ROLE_USER')]
    public function events(
        CleaningRequestRepository $repo,
        LeadRepository $leadRepo,
        GoogleCalendarService $googleCalendarService,
        Request $request
    ): JsonResponse {
        $cleanerId = $request->query->get('cleaner');
        $ownerId = $request->query->get('owner');

        $events = [];

        /* ─────────────────────────────
         * GOOGLE CALENDAR (ADMIN ONLY)
         * ───────────────────────────── */
        if ($this->isGranted('ROLE_ADMIN')) {
            try {
                $timeMin = new \DateTime('-30 days');
                $timeMax = new \DateTime('+90 days');
                $googleEvents = $googleCalendarService->listEvents($timeMin, $timeMax);

                foreach ($googleEvents as $gEvent) {
                    $start = $gEvent->getStart();
                    $startDateTime = $start->getDateTime() ?? $start->getDate();

                    if (!$startDateTime) {
                        continue;
                    }

                    $end = $gEvent->getEnd();
                    $endDateTime = $end ? ($end->getDateTime() ?? $end->getDate()) : null;

                    $events[] = [
                        'id' => 'gcal_' . $gEvent->getId(),
                        'title' => $gEvent->getSummary() ?: '(Sans titre)',
                        'start' => $startDateTime,
                        'end' => $endDateTime,

                        'backgroundColor' => $this->googleColorToHex($gEvent->getColorId()),
                        'borderColor' => $this->googleColorToHex($gEvent->getColorId()),
                        'textColor' => '#ffffff',

                        'classNames' => ['fc-mission-event', 'fc-status-pending'],
                        'extendedProps' => [
                            'source' => 'google',
                            'description' => $gEvent->getDescription(),
                        ],
                    ];
                }
            } catch (\Throwable $e) {
                // ignore Google errors
            }
        }

        /* ─────────────────────────────
         * SYMFONY CLEANING REQUESTS
         * ───────────────────────────── */
        $requests = $repo->findAll();

        foreach ($requests as $req) {

            if ($this->isGranted('ROLE_OWNER') && !$this->isGranted('ROLE_ADMIN')) {
                if ($req->getProperty()->getOwner()->getId() !== $this->getUser()->getId()) {
                    continue;
                }
            }

            if ($this->isGranted('ROLE_CLEANER') && !$this->isGranted('ROLE_ADMIN')) {
                if (!$req->getAssignedCleaner() ||
                    $req->getAssignedCleaner()->getId() !== $this->getUser()->getId()) {
                    continue;
                }
            }

            if ($cleanerId && (!$req->getAssignedCleaner() || $req->getAssignedCleaner()->getId() != $cleanerId)) {
                continue;
            }

            if ($ownerId && $req->getProperty()->getOwner()->getId() != $ownerId) {
                continue;
            }

            $color = $req->getProperty()->getColor();

            $events[] = [
                'id' => 'cr_' . $req->getId(),
                'title' => $req->getProperty()->getName(),
                'start' => $req->getScheduledDate()->format('Y-m-d') . 'T' . $req->getScheduledTime()->format('H:i:s'),

                'backgroundColor' => $color,
                'borderColor' => $color,
                'textColor' => '#ffffff',

                'classNames' => [
                    'fc-mission-event',
                    'fc-status-' . strtolower($req->getStatus())
                ],

                'extendedProps' => [
                    'status' => $req->getStatus(),
                    'service' => $req->getService()->getName(),
                    'cleaner' => $req->getAssignedCleaner()
                        ? $req->getAssignedCleaner()->getFirstname() . ' ' . $req->getAssignedCleaner()->getLastname()
                        : 'Non assigné',
                ],
            ];
        }

        /* ─────────────────────────────
         * LEADS (ADMIN ONLY)
         * ───────────────────────────── */
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

                    'extendedProps' => [
                        'type' => 'prospection',
                        'city' => $lead->getCity(),
                    ],
                ];
            }
        }

        return $this->json($events);
    }

    private function googleColorToHex(?string $colorId): string
    {
        return match ($colorId) {
            '1' => '#7986CB',
            '2' => '#33B679',
            '3' => '#8E24AA',
            '4' => '#E67C73',
            '5' => '#F6BF26',
            '6' => '#F4511E',
            '7' => '#039BE5',
            '8' => '#616161',
            '9' => '#3F51B5',
            '10' => '#0B8043',
            '11' => '#D50000',
            default => '#4285F4',
        };
    }

    // (le reste de tes méthodes moveEvent, open, details reste inchangé)
}