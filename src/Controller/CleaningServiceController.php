<?php

namespace App\Controller;

use App\Entity\CleaningService;
use App\Form\CleaningServiceType;
use App\Repository\CleaningServiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/services')]
#[IsGranted('ROLE_ADMIN')]
class CleaningServiceController extends AbstractController
{
    #[Route('', name: 'app_services')]
    public function index(CleaningServiceRepository $repo): Response
    {
        return $this->render('cleaning_service/index.html.twig', [
            'services' => $repo->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_service_new')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $service = new CleaningService();
        $form = $this->createForm(CleaningServiceType::class, $service);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($service);
            $em->flush();
            $this->addFlash('success', 'Prestation créée.');
            return $this->redirectToRoute('app_services');
        }

        return $this->render('cleaning_service/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_service_edit')]
    public function edit(CleaningService $service, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(CleaningServiceType::class, $service);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Prestation modifiée.');
            return $this->redirectToRoute('app_services');
        }

        return $this->render('cleaning_service/edit.html.twig', [
            'form' => $form->createView(),
            'service' => $service,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_service_delete', methods: ['POST'])]
    public function delete(CleaningService $service, EntityManagerInterface $em): Response
    {
        $em->remove($service);
        $em->flush();
        $this->addFlash('success', 'Prestation supprimée.');
        return $this->redirectToRoute('app_services');
    }
}