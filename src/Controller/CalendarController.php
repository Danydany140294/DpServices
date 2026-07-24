<?php

namespace App\Controller;

use App\Repository\CleaningRequestRepository;
use App\Entity\CleaningRequest;
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

        // Chargé une seule fois, réutilisé par les deux boucles (Google + missions locales).
        $requests = $repo->findAll();

        /* ─────────────────────────────
         * GOOGLE CALENDAR (ADMIN ONLY)
         * ───────────────────────────── */
        if ($this->isGranted('ROLE_ADMIN')
            || $this->isGranted('ROLE_OWNER'))
             {
            try {
                // Tout googleEventId déjà rattaché à une mission locale ne doit
                // JAMAIS être réaffiché comme événement Google "brut" : sinon on
                // obtient deux blocs (event Google + mission locale) pour la
                // même intervention, avec deux titres différents.
                $knownGoogleEventIds = array_flip(array_filter(
                    array_map(
                        fn($r) => $r->getGoogleEventId(),
                        $requests
                    )
                ));

                $timeMin = new \DateTime('-30 days');
                $timeMax = new \DateTime('+90 days');
                $googleEvents = $googleCalendarService->listEvents($timeMin, $timeMax);


                // Si un propriétaire est connecté, on mémorise son prénom.
// Il servira à filtrer les événements Google historiques
// (ex : "Julien ménage", "Manon ménage"...)
$ownerFirstname = null;

if ($this->isGranted('ROLE_OWNER') && !$this->isGranted('ROLE_ADMIN')) {
    $ownerFirstname = mb_strtolower(
        trim($this->getUser()->getFirstname())
    );
}

                foreach ($googleEvents as $gEvent) {
                    if (isset($knownGoogleEventIds[$gEvent->getId()])) {
                        // Déjà représenté par une mission locale (boucle suivante) : on ignore ici.
                        continue;
                    }

                    // Les propriétaires ne doivent voir que leurs événements Google.
// Pour les anciens événements, on se base sur le prénom contenu
// dans le titre.
if ($ownerFirstname !== null) {

    $summary = mb_strtolower(
        $gEvent->getSummary() ?? ''
    );

    if (!str_contains($summary, $ownerFirstname)) {
        continue;
    }
}

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
        foreach ($requests as $req) {

           
    // Les missions annulées ne doivent plus apparaître dans le calendrier.
    if ($req->isCancelled()) {
        continue;
    }

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

    #[Route('/api/calendar/events/{id}/move', name: 'app_calendar_move', methods: ['POST'])]
#[IsGranted('ROLE_ADMIN')]
public function moveEvent(
    CleaningRequest $request,
    Request $httpRequest,
    EntityManagerInterface $em
): JsonResponse {
    $data = json_decode($httpRequest->getContent(), true);

    if (empty($data['start'])) {
        return $this->json(['success' => false, 'error' => 'Date manquante'], 400);
    }

    try {
        $newStart = new \DateTime($data['start']);
        $request->setScheduledDate($newStart);
        $em->flush();

        return $this->json(['success' => true]);

    } catch (\Exception $e) {
        return $this->json(['success' => false, 'error' => $e->getMessage()], 500);
    }
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