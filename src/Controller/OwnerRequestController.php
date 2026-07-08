<?php

namespace App\Controller;

use App\Entity\CleaningRequest;
use App\Form\OwnerCleaningRequestType;
use App\Repository\PropertyRepository;
use App\Repository\UserRepository;
use App\Service\ActivityLogService;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/owner/requests')]
#[IsGranted('ROLE_OWNER')]
class OwnerRequestController extends AbstractController
{
    public function __construct(
        private ActivityLogService $logger,
        private NotificationService $notificationService,
        private UserRepository $userRepository,
    ) {
    }

    #[Route('/new', name: 'app_owner_request_new')]
    public function new(Request $request, EntityManagerInterface $em, PropertyRepository $propertyRepo): Response
    {
        $user = $this->getUser();
        $properties = $propertyRepo->findBy(['owner' => $user]);

        $cleaningRequest = new CleaningRequest();
        $cleaningRequest->setStatus('PENDING');

        $form = $this->createForm(OwnerCleaningRequestType::class, $cleaningRequest, [
            'properties' => $properties,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Sécurité : le logement choisi doit bien appartenir au propriétaire connecté
            if (!in_array($cleaningRequest->getProperty(), $properties, true)) {
                throw $this->createAccessDeniedException();
            }

            $em->persist($cleaningRequest);
            $em->flush();

            $this->logger->log(
                'Demande créée (propriétaire)',
                $cleaningRequest->getProperty()->getName() . ' — ' . $cleaningRequest->getScheduledDate()->format('d/m/Y')
            );

            $this->notifyAdmins(
                sprintf(
                    'Nouvelle demande de %s %s : %s le %s à %s',
                    $user->getFirstname(),
                    $user->getLastname(),
                    $cleaningRequest->getProperty()->getName(),
                    $cleaningRequest->getScheduledDate()->format('d/m/Y'),
                    $cleaningRequest->getScheduledTime()->format('H:i')
                ),
                'request_created_by_owner'
            );

            $this->addFlash('success', 'Votre demande a été envoyée.');
            return $this->redirectToRoute('app_owner_requests');
        }

        return $this->render('owner/request_new.html.twig', ['form' => $form->createView()]);
    }

    #[Route('/{id}/edit', name: 'app_owner_request_edit')]
    public function edit(CleaningRequest $cleaningRequest, Request $request, EntityManagerInterface $em, PropertyRepository $propertyRepo): Response
    {
        $this->denyAccessUnlessOwner($cleaningRequest);

        if ($cleaningRequest->getStatus() !== 'PENDING') {
            $this->addFlash('error', 'Cette demande a déjà été validée, elle ne peut plus être modifiée.');
            return $this->redirectToRoute('app_owner_requests');
        }

        $user = $this->getUser();
        $properties = $propertyRepo->findBy(['owner' => $user]);

        $form = $this->createForm(OwnerCleaningRequestType::class, $cleaningRequest, [
            'properties' => $properties,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!in_array($cleaningRequest->getProperty(), $properties, true)) {
                throw $this->createAccessDeniedException();
            }

            $em->flush();

            $this->logger->log(
                'Demande modifiée (propriétaire)',
                $cleaningRequest->getProperty()->getName() . ' — ' . $cleaningRequest->getScheduledDate()->format('d/m/Y')
            );
            $this->addFlash('success', 'Votre demande a été mise à jour.');
            return $this->redirectToRoute('app_owner_requests');
        }

        return $this->render('owner/request_edit.html.twig', [
            'form' => $form->createView(),
            'cleaningRequest' => $cleaningRequest,
        ]);
    }

    #[Route('/{id}/cancel', name: 'app_owner_request_cancel', methods: ['POST'])]
    public function cancel(CleaningRequest $cleaningRequest, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessOwner($cleaningRequest);

        if (!$this->isCsrfTokenValid('owner_cancel' . $cleaningRequest->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('app_owner_requests');
        }

        if (in_array($cleaningRequest->getStatus(), ['CANCELLED', 'COMPLETED'], true)) {
            $this->addFlash('error', 'Cette demande ne peut plus être annulée.');
            return $this->redirectToRoute('app_owner_requests');
        }

        $cleaningRequest->setStatus('CANCELLED');
        $em->flush();

        $user = $this->getUser();

        // Notifie la femme de ménage assignée, si il y en avait une
        if ($cleaningRequest->getAssignedCleaner()) {
            $this->notificationService->notifyMissionCancelled($cleaningRequest->getAssignedCleaner(), $cleaningRequest);
        }

        // Notifie tous les admins
        $this->notifyAdmins(
            sprintf(
                'Demande annulée par %s %s : %s le %s à %s',
                $user->getFirstname(),
                $user->getLastname(),
                $cleaningRequest->getProperty()->getName(),
                $cleaningRequest->getScheduledDate()->format('d/m/Y'),
                $cleaningRequest->getScheduledTime()->format('H:i')
            ),
            'request_cancelled_by_owner'
        );

        $this->logger->log(
            'Demande annulée (propriétaire)',
            $cleaningRequest->getProperty()->getName() . ' — ' . $cleaningRequest->getScheduledDate()->format('d/m/Y')
        );
        $this->addFlash('success', 'Votre demande a été annulée.');

        return $this->redirectToRoute('app_owner_requests');
    }

    private function denyAccessUnlessOwner(CleaningRequest $cleaningRequest): void
    {
        $property = $cleaningRequest->getProperty();
        if (!$property || $property->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }
    }

    private function notifyAdmins(string $message, string $type): void
    {
        $admins = $this->userRepository->findByRole('ROLE_ADMIN');
        foreach ($admins as $admin) {
            $this->notificationService->notify(
                $admin,
                $type,
                $message,
                $this->generateUrl('app_requests')
            );
        }
    }
}