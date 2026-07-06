<?php

namespace App\Controller;

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

        $pending = array_filter($requests, fn($r) => $r->getStatus() === 'PENDING');
        $validated = array_filter($requests, fn($r) => $r->getStatus() === 'VALIDATED');
        $completed = array_filter($requests, fn($r) => $r->getStatus() === 'COMPLETED');
        $cancelled = array_filter($requests, fn($r) => $r->getStatus() === 'CANCELLED');

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
                && $r->getStatus() !== 'CANCELLED';
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
            if ($r->getScheduledDate() === null || $r->getStatus() === 'CANCELLED') {
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
                'tone' => in_array($mission->getStatus(), ['VALIDATED', 'COMPLETED'], true) ? 'gold' : 'teal',
                'title' => $mission->getService()?->getName() ?? 'Prestation',
                'place' => $mission->getProperty()?->getName() ?? '',
            ];
        }

        // -- Ajout des événements Google Calendar de la semaine --
        try {
            $googleEvents = $googleCalendarService->listEvents($weekStart, $weekEndExclusive);
            foreach ($googleEvents as $gEvent) {
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
    'tone' => 'gold',
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
                        'tone' => $item['tone'],
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
                    'tone' => $item['tone'],
                    'title' => $item['title'],
                    'place' => $item['place'],
                    
                ];
                continue;
            }

            // -- 2 ou 3 missions : empilées verticalement, titre seul, sans
            //    tenir compte de l'heure réelle pour la position (on les
            //    répartit simplement du haut vers le bas de la grille). --
            foreach ($missionsThatDay as $index => $item) {
                $evenements[] = [
                    'day' => $day,
                    'mode' => 'stacked',
                    'top' => 5 + ($index * 30),
                    'tone' => $item['tone'],
                    'title' => $item['title'],
                    
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
}