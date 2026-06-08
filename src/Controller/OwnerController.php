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

        return $this->render('owner/index.html.twig', [
            'properties' => $properties,
            'requests' => $requests,
        ]);
    }
}