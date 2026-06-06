<?php

namespace App\Controller;

use App\Entity\CleaningRequest;
use App\Form\CleaningRequestType;
use App\Repository\CleaningRequestRepository;
use App\Repository\UserRepository;
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
    #[Route('', name: 'app_requests')]
    public function index(CleaningRequestRepository $repo): Response
    {
        return $this->render('cleaning_request/index.html.twig', [
            'requests' => $repo->findBy([], ['scheduledDate' => 'ASC']),
        ]);
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
            $this->addFlash('success', 'Demande créée.');
            return $this->redirectToRoute('app_requests');
        }

        return $this->render('cleaning_request/new.html.twig', [
            'form' => $form->createView(),
        ]);
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
            $this->addFlash('success', 'Demande validée.');
            return $this->redirectToRoute('app_requests');
        }

        return $this->render('cleaning_request/validate.html.twig', [
            'form' => $form->createView(),
            'cleaningRequest' => $cleaningRequest,
        ]);
    }

    #[Route('/{id}/complete', name: 'app_request_complete', methods: ['POST'])]
    public function complete(CleaningRequest $cleaningRequest, EntityManagerInterface $em): Response
    {
        $cleaningRequest->setStatus('COMPLETED');
        $em->flush();
        $this->addFlash('success', 'Demande marquée comme terminée.');
        return $this->redirectToRoute('app_requests');
    }

    #[Route('/{id}/cancel', name: 'app_request_cancel', methods: ['POST'])]
    public function cancel(CleaningRequest $cleaningRequest, EntityManagerInterface $em): Response
    {
        $cleaningRequest->setStatus('CANCELLED');
        $em->flush();
        $this->addFlash('success', 'Demande annulée.');
        return $this->redirectToRoute('app_requests');
    }
}