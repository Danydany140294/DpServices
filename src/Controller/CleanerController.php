<?php

namespace App\Controller;

use App\Repository\CleaningRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Entity\CleaningRequest;

#[Route('/cleaner')]
#[IsGranted('ROLE_CLEANER')]
class CleanerController extends AbstractController
{
    #[Route('', name: 'app_cleaner')]
    public function index(CleaningRequestRepository $repo): Response
    {
        $user = $this->getUser();
        $today = new \DateTime('today');
        $missions = $repo->findUpcomingForCleaner($user, $today);

        return $this->render('cleaner/index.html.twig', [
            'missions' => $missions,
            'today' => $today,
        ]);
    }

    #[Route('/{id}/complete', name: 'app_cleaner_complete', methods: ['POST'])]
    public function complete(CleaningRequest $mission, EntityManagerInterface $em): Response
    {
        $mission->setStatus('COMPLETED');
        $em->flush();
        $this->addFlash('success', 'Mission marquée comme terminée !');
        return $this->redirectToRoute('app_cleaner');
    }

    #[Route('/history', name: 'app_cleaner_history')]
    public function history(CleaningRequestRepository $repo): Response
    {
        $missions = $repo->findCompletedForCleaner($this->getUser());

        return $this->render('cleaner/history.html.twig', [
            'missions' => $missions,
        ]);
    }
}