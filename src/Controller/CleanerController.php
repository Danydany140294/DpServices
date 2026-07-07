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

        // Nombre de missions prévues aujourd'hui (toutes statuts confondus,
        // hors annulées si tu as ce statut — à ajuster si besoin)
        $todayMissionsCount = 0;
        foreach ($missions as $mission) {
            if ($mission->getScheduledDate()->format('Y-m-d') === $today->format('Y-m-d')) {
                $todayMissionsCount++;
            }
        }

        // Prochaine mission = la première de la liste triée par date/heure,
        // en excluant celles déjà terminées
        $nextMission = null;
        foreach ($missions as $mission) {
            if ($mission->getStatus() !== 'COMPLETED') {
                $nextMission = $mission;
                break;
            }
        }

        return $this->render('cleaner/index.html.twig', [
            'missions' => $missions,
            'today' => $today,
            'todayMissionsCount' => $todayMissionsCount,
            'nextMission' => $nextMission,
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
    'today' => new \DateTime('today'),
]);
    }

    #[Route('/messages', name: 'app_cleaner_messages')]
    public function messages(): Response
    {
        // Page vide pour l'instant — squelette prêt à recevoir
        // une vraie liste de conversations plus tard
        return $this->render('cleaner/messages.html.twig');
    }

    #[Route('/profile', name: 'app_cleaner_profile')]
    public function profile(): Response
    {
        return $this->render('cleaner/profile.html.twig', [
            'user' => $this->getUser(),
           
    'today' => new \DateTime('today'),
        ]);
    }
}