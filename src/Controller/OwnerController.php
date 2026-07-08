<?php

namespace App\Controller;

use App\Repository\CleaningRequestRepository;
use App\Repository\PropertyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/owner')]
#[IsGranted('ROLE_OWNER')]
class OwnerController extends AbstractController
{
    #[Route('', name: 'app_owner')]
    public function index(PropertyRepository $propertyRepo, CleaningRequestRepository $requestRepo): Response
    {
        $user = $this->getUser();
        $properties = $propertyRepo->findBy(['owner' => $user]);

        // Vérifier l'accès à chaque logement via le Voter
        foreach ($properties as $property) {
            $this->denyAccessUnlessGranted('view', $property);
        }

        $requests = $requestRepo->findBy(['property' => $properties], ['scheduledDate' => 'ASC']);

        // ── Données pour le dashboard enrichi ──
        $pendingCount = count(array_filter($requests, fn($r) => $r->getStatus() === 'PENDING'));

        $today = new \DateTime('today');
        $upcoming = array_filter($requests, function ($r) use ($today) {
            return $r->getStatus() !== 'CANCELLED'
                && $r->getStatus() !== 'COMPLETED'
                && $r->getScheduledDate() !== null
                && $r->getScheduledDate() >= $today;
        });
        $upcomingCount = count($upcoming);

        // La prochaine intervention à venir (déjà triée par scheduledDate ASC via le repo).
        usort($upcoming, fn($a, $b) => $a->getScheduledDate() <=> $b->getScheduledDate()
            ?: $a->getScheduledTime() <=> $b->getScheduledTime());
        $nextRequest = $upcoming[0] ?? null;

        return $this->render('owner/index.html.twig', [
            'properties' => $properties,
            'requests' => $requests,
            'pendingCount' => $pendingCount,
            'upcomingCount' => $upcomingCount,
            'nextRequest' => $nextRequest,
        ]);
    }

    #[Route('/calendar', name: 'app_owner_calendar')]
    public function ownerCalendar(): Response
    {
        return $this->render('owner/calendar.html.twig');
    }
}