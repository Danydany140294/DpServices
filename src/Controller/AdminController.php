<?php

namespace App\Controller;

use App\Entity\CleaningRequest;
use App\Repository\ActivityLogRepository;
use App\Repository\CleaningRequestRepository;
use App\Repository\PropertyRepository;
use App\Repository\UserRepository;
use App\Service\GoogleCalendarService;
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
        CleaningRequestRepository $requestRepo,
        ActivityLogRepository $logRepo,
        GoogleCalendarService $googleCalendarService
    ): Response {
        $owners = $userRepo->findByRole('ROLE_OWNER');
        $cleaners = $userRepo->findByRole('ROLE_CLEANER');
        $properties = $propertyRepo->findAll();
        $requests = $requestRepo->findAll();

        $pending = array_filter($requests, fn($r) => $r->getStatus() === CleaningRequest::STATUS_PENDING);
        $validated = array_filter($requests, fn($r) => $r->getStatus() === CleaningRequest::STATUS_VALIDATED);
        $completed = array_filter($requests, fn($r) => $r->getStatus() === CleaningRequest::STATUS_COMPLETED);
        $cancelled = array_filter($requests, fn($r) => $r->getStatus() === CleaningRequest::STATUS_CANCELLED);

        // -- Demandes à valider (dashboard) --
        // Les plus anciennes en premier (les plus urgentes à traiter),
        // limitées aux 5 premières pour ne pas surcharger le bloc.
        $pendingSorted = $pending;
        usort($pendingSorted, fn($a, $b) => $a->getScheduledDate() <=> $b->getScheduledDate());
        $demandesAValider = array_slice($pendingSorted, 0, 5);

        // -- Missions assignées aujourd'hui (dashboard) --
        // scheduledDate == aujourd'hui, tous statuts sauf CANCELLED.
        $today = new \DateTime('today');
        $missionsAujourdhuiList = array_filter($requests, function ($r) use ($today) {
            return $r->getScheduledDate() !== null
                && $r->getScheduledDate()->format('Y-m-d') === $today->format('Y-m-d')
                && !$r->isCancelled();
        });
        // Tri par heure de passage croissante.
        usort($missionsAujourdhuiList, fn($a, $b) => $a->getScheduledTime() <=> $b->getScheduledTime());

        // -- Historique récent (dashboard) --
        // Les 4 derniers événements (la requête du repo trie déjà par
        // createdAt DESC, on se contente de limiter côté contrôleur ;
        // si le volume devient important, préférer un vrai LIMIT en
        // base via une méthode dédiée du repository).
        $logsRecents = array_slice($logRepo->findBy([], ['createdAt' => 'DESC'], 4), 0, 4);

        // ════════════════════════════════════════════════════════════
        // -- Mini calendrier hebdomadaire (dashboard) --
        // Calcule la semaine en cours (lundi -> dimanche) et transforme
        // les CleaningRequest + événements Google Calendar de cette
        // semaine en événements pour le widget .dash-cal-* du dashboard.
        //
        // ⚠️ Couleur : on utilise désormais la couleur du logement
        // (Property::getColor()) — exactement la même source que la
        // page Calendrier (CalendarController::events(), $req->getProperty()->getColor()) —
        // au lieu de l'ancien mapping "gold si validé/terminé, teal sinon".
        // Les événements Google Calendar reprennent la même palette que
        // googleColorToHex() de CalendarController.
        //
        // ⚠️ Dédoublonnage : un CleaningRequest synchronisé avec Google
        // Calendar (getGoogleEventId() renseigné) ne doit JAMAIS être
        // compté une seconde fois via l'événement Google brut associé —
        // même logique que CalendarController::events() (voir
        // $knownGoogleEventIds ci-dessous), sinon le nombre de missions
        // par jour est gonflé et incohérent avec la page /calendar.
        //
        // Règles d'affichage selon le nombre de missions du même jour :
        //   1 mission        -> positionnée selon l'heure, titre + logement
        //   2 ou 3 missions  -> empilées verticalement, titre seul
        //   4 missions ou +  -> pastille sur le jour + liste détaillée
        //                       sous la grille (missionsDebordantes)
        // ════════════════════════════════════════════════════════════

        $isoDayOfWeek = (int) $today->format('N'); // 1 = lundi ... 7 = dimanche
        $weekStart = (clone $today)->modify('-' . ($isoDayOfWeek - 1) . ' days');
        $weekEnd = (clone $weekStart)->modify('+6 days');
        $weekEndExclusive = (clone $weekEnd)->modify('+1 day');

        $dayLabels = ['LUN', 'MAR', 'MER', 'JEU', 'VEN', 'SAM', 'DIM'];

        // -- Missions de la semaine en cours, tous statuts sauf CANCELLED --
        $missionsWeek = array_filter($requests, function ($r) use ($weekStart, $weekEndExclusive) {
            if ($r->getScheduledDate() === null || $r->isCancelled()) {
                return false;
            }

            return $r->getScheduledDate() >= $weekStart && $r->getScheduledDate() < $weekEndExclusive;
        });

        // -- Normalisation : CleaningRequest -> tableau simple --
        $normalized = [];
        foreach ($missionsWeek as $mission) {
            $normalized[] = [
                'date' => $mission->getScheduledDate(),
                'time' => $mission->getScheduledTime(),
                'color' => $mission->getProperty()?->getColor() ?? '#E8B84A',
                'title' => $mission->getService()?->getName() ?? 'Prestation',
                'place' => $mission->getProperty()?->getName() ?? '',
            ];
        }

        // -- IDs des événements Google déjà représentés par une mission
        //    locale : identique à CalendarController::events(), pour ne
        //    jamais réafficher deux fois la même intervention. --
        $knownGoogleEventIds = array_flip(array_filter(
            array_map(fn($r) => $r->getGoogleEventId(), $requests)
        ));

        // -- Ajout des événements Google Calendar de la semaine --
        try {
            $googleEvents = $googleCalendarService->listEvents($weekStart, $weekEndExclusive);
            foreach ($googleEvents as $gEvent) {
                // Déjà représenté par une mission locale : on ignore.
                if (isset($knownGoogleEventIds[$gEvent->getId()])) {
                    continue;
                }

                $start = $gEvent->getStart();
                $startDateTime = $start->getDateTime() ?? $start->getDate();

                if ($startDateTime === null) {
                    continue;
                }

                $dt = new \DateTime($startDateTime);
                // On extrait uniquement la date calendaire (Y-m-d), en ignorant le fuseau
                // horaire de l'événement, pour que le regroupement par jour soit cohérent
                // avec $weekStart (qui utilise le fuseau par défaut du serveur).
                $dateOnly = new \DateTime($dt->format('Y-m-d'));

                $normalized[] = [
                    'date' => $dateOnly,
                    'time' => $dt,
                    'color' => $this->googleColorToHex($gEvent->getColorId()),
                    'title' => $gEvent->getSummary() ?: '(Sans titre)',
                    'place' => '',
                ];
            }
        } catch (\Throwable $e) {
            // Le dashboard reste utilisable même si Google est indisponible.
        }

        // -- Regroupe par jour (0 = lundi ... 6 = dimanche) --
        $missionsByDay = [];
        foreach ($normalized as $item) {
            $day = (int) $weekStart->diff($item['date'])->days;
            if ($day < 0 || $day > 6) {
                continue;
            }
            $missionsByDay[$day][] = $item;
        }

        // -- Tableau "jours" pour l'en-tête du mini calendrier --
        // (overflow = true si 4+ missions ce jour-là -> affiche une pastille)
        $jours = [];
        for ($i = 0; $i < 7; $i++) {
            $date = (clone $weekStart)->modify("+{$i} days");
            $jours[] = [
                'code' => $dayLabels[$i],
                'num' => (int) $date->format('j'),
                'active' => $date->format('Y-m-d') === $today->format('Y-m-d'),
                'overflow' => isset($missionsByDay[$i]) && count($missionsByDay[$i]) >= 4,
            ];
        }

        // La grille visuelle du mini calendrier couvre 08h00 -> 20h00 (12h).
        $calendarStartHour = 8;
        $calendarEndHour = 20;

        $evenements = [];
        $missionsDebordantes = [];

        foreach ($missionsByDay as $day => $missionsThatDay) {
            // Tri par heure croissante (ordre d'empilement / d'affichage).
            usort($missionsThatDay, fn($a, $b) => $a['time'] <=> $b['time']);
            $count = count($missionsThatDay);

            // -- 4 missions ou plus : pas de rendu dans la grille, tout part
            //    dans la liste "missionsDebordantes" affichée sous le calendrier. --
            if ($count >= 4) {
                foreach ($missionsThatDay as $item) {
                    $missionsDebordantes[] = [
                        'dayLabel' => $dayLabels[$day],
                        'time' => $item['time']->format('H:i'),
                        'color' => $item['color'],
                        'title' => $item['title'],
                        'place' => $item['place'],
                    ];
                }
                continue;
            }

            // -- 1 seule mission : comportement inchangé, positionnée selon l'heure --
            if ($count === 1) {
                $item = $missionsThatDay[0];
                $time = $item['time'];
                $hourDecimal = (int) $time->format('H') + ((int) $time->format('i') / 60);
                $top = ($hourDecimal - $calendarStartHour) / ($calendarEndHour - $calendarStartHour) * 100;
                $top = max(0, min(95, $top));

                $evenements[] = [
                    'day' => $day,
                    'mode' => 'positioned',
                    'top' => round($top, 1),
                    'color' => $item['color'],
                    'title' => $item['title'],
                    'place' => $item['place'],
                ];
                continue;
            }

            // -- 2 ou 3 missions : même heure moyenne (position verticale
            //    commune), mais réparties côte à côte horizontalement dans
            //    la colonne du jour pour ne jamais se chevaucher, même
            //    quand elles ont exactement la même heure. --
            $count2 = count($missionsThatDay);
            $avgHourDecimal = array_sum(array_map(
                fn($item) => (int) $item['time']->format('H') + ((int) $item['time']->format('i') / 60),
                $missionsThatDay
            )) / $count2;
            $sharedTop = ($avgHourDecimal - $calendarStartHour) / ($calendarEndHour - $calendarStartHour) * 100;
            $sharedTop = max(0, min(90, $sharedTop));

            $gap = 2; // % d'écart entre deux blocs
            $slotWidth = round((96 - $gap * ($count2 - 1)) / $count2, 1);

            foreach ($missionsThatDay as $index => $item) {
                $evenements[] = [
                    'day' => $day,
                    'mode' => 'stacked',
                    'top' => round($sharedTop, 1),
                    'left' => round(2 + $index * ($slotWidth + $gap), 1),
                    'width' => $slotWidth,
                    'color' => $item['color'],
                    'title' => $item['title'],
                    'place' => $item['place'],
                ];
            }
        }

        return $this->render('admin/index.html.twig', [
            'totalOwners' => count($owners),
            'totalCleaners' => count($cleaners),
            'totalProperties' => count($properties),
            'totalRequests' => count($requests),
            'pendingRequests' => count($pending),
            'validatedRequests' => count($validated),
            'completedRequests' => count($completed),
            'cancelledRequests' => count($cancelled),

            // ⚠️ Nouvelles données pour le dashboard enrichi.
            // Ce sont des entités CleaningRequest / ActivityLog brutes,
            // pas des tableaux pré-formatés — le twig accède directement
            // à leurs getters (getProperty(), getScheduledDate(), etc.)
            'demandesAValider' => $demandesAValider,
            'missionsAssigneesToday' => $missionsAujourdhuiList,
            'missionsAujourdhuiCount' => count($missionsAujourdhuiList),
            'logsRecents' => $logsRecents,

            // -- Mini calendrier hebdomadaire --
            'jours' => $jours,
            'evenements' => $evenements,
            'missionsDebordantes' => $missionsDebordantes,
        ]);
    }

    /**
     * Identique à CalendarController::googleColorToHex() — même palette
     * pour que les événements Google Calendar aient la même couleur
     * sur le dashboard que sur la vraie page Calendrier.
     */
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
}
