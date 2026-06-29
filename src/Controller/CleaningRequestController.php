<?php

namespace App\Controller;

use App\Entity\CleaningRequest;
use App\Form\CleaningRequestType;
use App\Repository\CleaningRequestRepository;
use App\Repository\UserRepository;
use App\Service\ActivityLogService;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/requests')]
#[IsGranted('ROLE_ADMIN')]
class CleaningRequestController extends AbstractController
{
    public function __construct(
        private ActivityLogService $logger,
        private EmailService $emailService,
        private \App\Service\GoogleSyncService $googleSyncService,
        private \App\Service\NotificationService $notificationService
    ) {}

    #[Route('', name: 'app_requests')]
    public function index(CleaningRequestRepository $repo, Request $request, \Knp\Component\Pager\PaginatorInterface $paginator): Response
    {
        $status = $request->query->get('status');
        $date = $request->query->get('date');
        $query = $repo->findWithFiltersQuery($status, $date);
        $requests = $paginator->paginate($query, $request->query->getInt('page', 1), 10);

        return $this->render('cleaning_request/index.html.twig', [
            'requests' => $requests,
            'currentStatus' => $status,
            'currentDate' => $date,
        ]);
    }

    #[Route('/pending', name: 'app_requests_pending')]
    public function pending(CleaningRequestRepository $repo): Response
    {
        $pendingRequests = $repo->findBy(['needsConfirmation' => true]);

        return $this->render('cleaning_request/pending.html.twig', [
            'requests' => $pendingRequests,
        ]);
    }

    #[Route('/{id}/accept-modification', name: 'app_request_accept_modification', methods: ['POST'])]
    public function acceptModification(
        CleaningRequest $cleaningRequest,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        if (!$this->isCsrfTokenValid('accept' . $cleaningRequest->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('app_requests_pending');
        }

        if ($cleaningRequest->getPendingScheduledDate() !== null) {
            $cleaningRequest->setScheduledDate($cleaningRequest->getPendingScheduledDate());
        }
        if ($cleaningRequest->getPendingScheduledTime() !== null) {
            $cleaningRequest->setScheduledTime($cleaningRequest->getPendingScheduledTime());
        }
        if ($cleaningRequest->getPendingComment() !== null) {
            $cleaningRequest->setComment($cleaningRequest->getPendingComment());
        }

        $cleaningRequest->setPendingScheduledDate(null);
        $cleaningRequest->setPendingScheduledTime(null);
        $cleaningRequest->setPendingComment(null);
        $cleaningRequest->setNeedsConfirmation(false);
        $cleaningRequest->setStatus('VALIDATED');
        $cleaningRequest->setSyncStatus('synced');
        $cleaningRequest->setLastSyncAt(new \DateTime());

        $em->flush();

        $this->logger->log('Modification Google acceptée', $cleaningRequest->getProperty()->getName() . ' — ' . $cleaningRequest->getScheduledDate()->format('d/m/Y'));
        $this->addFlash('success', 'Modification acceptée.');

        return $this->redirectToRoute('app_requests_pending');
    }

    #[Route('/{id}/reject-modification', name: 'app_request_reject_modification', methods: ['POST'])]
    public function rejectModification(
        CleaningRequest $cleaningRequest,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        if (!$this->isCsrfTokenValid('reject' . $cleaningRequest->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('app_requests_pending');
        }

        $cleaningRequest->setPendingScheduledDate(null);
        $cleaningRequest->setPendingScheduledTime(null);
        $cleaningRequest->setPendingComment(null);
        $cleaningRequest->setNeedsConfirmation(false);
        $cleaningRequest->setStatus('VALIDATED');

        // Remet l'event Google à l'état actuel de la mission (J26)
        $cleaningRequest->setSyncInProgress(true);
        $em->flush();

        try {
            $this->googleSyncService->revertGoogleEvent($cleaningRequest);
        } finally {
            $cleaningRequest->setSyncInProgress(false);
            $cleaningRequest->setLastSyncAt(new \DateTime());
            $em->flush();
        }

        $this->logger->log('Modification Google refusée', $cleaningRequest->getProperty()->getName() . ' — ' . $cleaningRequest->getScheduledDate()->format('d/m/Y'));
        $this->addFlash('success', 'Modification refusée, Google Agenda remis à jour.');

        return $this->redirectToRoute('app_requests_pending');
    }

    #[Route('/new', name: 'app_request_new')]
    public function new(Request $request, EntityManagerInterface $em, UserRepository $userRepository): Response
    {
        $cleaningRequest = new CleaningRequest();
        $cleaningRequest->setStatus('PENDING');
        $cleaners = $userRepository->findByRole('ROLE_CLEANER');
        $form = $this->createForm(CleaningRequestType::class, $cleaningRequest, ['cleaners' => $cleaners]);
        $form->handleRequest($request);

       if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($cleaningRequest);
            $em->flush();

            $this->googleSyncService->pushCreate($cleaningRequest);

            if ($cleaningRequest->getAssignedCleaner()) {
                $this->notificationService->notifyMissionAssigned(
                    $cleaningRequest->getAssignedCleaner(),
                    $cleaningRequest,
                    $this->generateUrl('app_calendar')
                );
            }

            $this->logger->log('Demande créée', $cleaningRequest->getProperty()->getName() . ' — ' . $cleaningRequest->getScheduledDate()->format('d/m/Y'));
            $this->addFlash('success', 'Demande créée.');
            return $this->redirectToRoute('app_requests');
        }

        return $this->render('cleaning_request/new.html.twig', ['form' => $form->createView()]);
    }

    #[Route('/{id}/validate', name: 'app_request_validate')]
    public function validate(CleaningRequest $cleaningRequest, Request $request, EntityManagerInterface $em, UserRepository $userRepository): Response
    {
        $cleaners = $userRepository->findByRole('ROLE_CLEANER');
        $form = $this->createForm(CleaningRequestType::class, $cleaningRequest, ['cleaners' => $cleaners]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $cleaningRequest->setStatus('VALIDATED');
            $em->flush();

            if ($cleaningRequest->getAssignedCleaner()) {
                try {
                    $this->emailService->sendMissionAssigned(
                        $cleaningRequest->getAssignedCleaner()->getEmail(),
                        $cleaningRequest->getAssignedCleaner()->getFirstname(),
                        $cleaningRequest->getProperty()->getName(),
                        $cleaningRequest->getScheduledDate()->format('d/m/Y'),
                        $cleaningRequest->getScheduledTime()->format('H:i')
                    );
                } catch (\Exception $e) {
                    // Email non bloquant
                }
            }

            $this->logger->log('Demande validée', $cleaningRequest->getProperty()->getName() . ' — ' . $cleaningRequest->getScheduledDate()->format('d/m/Y'));
            $this->addFlash('success', 'Demande validée.');
            return $this->redirectToRoute('app_requests');
        }

        return $this->render('cleaning_request/validate.html.twig', [
            'form' => $form->createView(),
            'cleaningRequest' => $cleaningRequest,
        ]);
    }

    #[Route('/{id}/complete', name: 'app_request_complete', methods: ['POST'])]
    public function complete(CleaningRequest $cleaningRequest, EntityManagerInterface $em, Request $request): Response
    {
        if ($this->isCsrfTokenValid('complete' . $cleaningRequest->getId(), $request->request->get('_token'))) {
            $cleaningRequest->setStatus('COMPLETED');
            $em->flush();
            $this->logger->log('Demande terminée', $cleaningRequest->getProperty()->getName() . ' — ' . $cleaningRequest->getScheduledDate()->format('d/m/Y'));
            $this->addFlash('success', 'Demande marquée comme terminée.');
        }
        return $this->redirectToRoute('app_requests');
    }

   #[Route('/{id}/cancel', name: 'app_request_cancel', methods: ['POST'])]
    public function cancel(CleaningRequest $cleaningRequest, EntityManagerInterface $em, Request $request): Response
    {
        if ($this->isCsrfTokenValid('cancel' . $cleaningRequest->getId(), $request->request->get('_token'))) {
            $cleaningRequest->setStatus('CANCELLED');
            $em->flush();

            // J34 : suppression croisée -> l'annulation côté app supprime l'event Google.
            $this->googleSyncService->pushDelete($cleaningRequest);

            $this->logger->log('Demande annulée', $cleaningRequest->getProperty()->getName() . ' — ' . $cleaningRequest->getScheduledDate()->format('d/m/Y'));
            $this->addFlash('success', 'Demande annulée.');
        }
        return $this->redirectToRoute('app_requests');
    }
}