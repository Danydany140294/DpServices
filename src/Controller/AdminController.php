<?php

namespace App\Controller;

use App\Repository\ActivityLogRepository;
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
        CleaningRequestRepository $requestRepo,
        ActivityLogRepository $logRepo
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
        ]);
    }
}