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

        foreach ($properties as $property) {
            $this->denyAccessUnlessGranted('view', $property);
        }

        $requests = $requestRepo->findBy(['property' => $properties], ['scheduledDate' => 'ASC']);

        $pendingCount = count(array_filter($requests, fn($r) => $r->getStatus() === 'PENDING'));

        $today = new \DateTime('today');
        $upcoming = array_filter($requests, function ($r) use ($today) {
            return $r->getStatus() !== 'CANCELLED'
                && $r->getStatus() !== 'COMPLETED'
                && $r->getScheduledDate() !== null
                && $r->getScheduledDate() >= $today;
        });
        $upcomingCount = count($upcoming);

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

    #[Route('/properties', name: 'app_owner_properties')]
    public function myProperties(PropertyRepository $propertyRepo): Response
    {
        $user = $this->getUser();
        $properties = $propertyRepo->findBy(['owner' => $user]);

        foreach ($properties as $property) {
            $this->denyAccessUnlessGranted('view', $property);
        }

        return $this->render('owner/properties.html.twig', [
            'properties' => $properties,
        ]);
    }

    #[Route('/requests', name: 'app_owner_requests')]
    public function myRequests(PropertyRepository $propertyRepo, CleaningRequestRepository $requestRepo): Response
    {
        $user = $this->getUser();
        $properties = $propertyRepo->findBy(['owner' => $user]);

        foreach ($properties as $property) {
            $this->denyAccessUnlessGranted('view', $property);
        }

        $requests = $requestRepo->findBy(['property' => $properties], ['scheduledDate' => 'DESC']);

        return $this->render('owner/requests.html.twig', [
            'requests' => $requests,
        ]);
    }
}