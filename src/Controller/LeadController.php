<?php

namespace App\Controller;

use App\Entity\Lead;
use App\Form\LeadType;
use App\Repository\LeadCategoryRepository;
use App\Repository\LeadRepository;
use App\Service\ActivityLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/acquisition/leads')]
#[IsGranted('ROLE_ADMIN')]
class LeadController extends AbstractController
{
    #[Route('', name: 'app_leads')]
    public function index(LeadRepository $repo, LeadCategoryRepository $categoryRepo, Request $request): Response
    {
        $status = $request->query->get('status');
        $city = $request->query->get('city');
        $categoryId = $request->query->get('category');

        $leads = $repo->findWithFilters($status, $city, $categoryId);
        $categories = $categoryRepo->findAll();

        return $this->render('lead/index.html.twig', [
            'leads' => $leads,
            'categories' => $categories,
            'currentStatus' => $status,
            'currentCity' => $city,
            'currentCategory' => $categoryId,
        ]);
    }

    #[Route('/new', name: 'app_lead_new')]
    public function new(Request $request, EntityManagerInterface $em, LeadCategoryRepository $categoryRepo, ActivityLogService $logger): Response
    {
        $lead = new Lead();
        $form = $this->createForm(LeadType::class, $lead, [
            'categories' => $categoryRepo->findAll(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($lead);
            $em->flush();
            $logger->log('Lead créé', $lead->getCompanyName() . ' — ' . $lead->getCity());
            $this->addFlash('success', 'Prospect créé avec succès.');
            return $this->redirectToRoute('app_leads');
        }

        return $this->render('lead/new.html.twig', ['form' => $form->createView()]);
    }

    #[Route('/{id}', name: 'app_lead_show')]
    public function show(Lead $lead): Response
    {
        return $this->render('lead/show.html.twig', ['lead' => $lead]);
    }

    #[Route('/{id}/edit', name: 'app_lead_edit')]
    public function edit(Lead $lead, Request $request, EntityManagerInterface $em, LeadCategoryRepository $categoryRepo, ActivityLogService $logger): Response
    {
        $form = $this->createForm(LeadType::class, $lead, [
            'categories' => $categoryRepo->findAll(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $logger->log('Lead modifié', $lead->getCompanyName());
            $this->addFlash('success', 'Prospect modifié.');
            return $this->redirectToRoute('app_lead_show', ['id' => $lead->getId()]);
        }

        return $this->render('lead/edit.html.twig', ['form' => $form->createView(), 'lead' => $lead]);
    }

    #[Route('/{id}/status/{status}', name: 'app_lead_status', methods: ['POST'])]
    public function changeStatus(Lead $lead, string $status, EntityManagerInterface $em, ActivityLogService $logger): Response
    {
        $lead->setStatus($status);
        $em->flush();
        $logger->log('Statut lead modifié', $lead->getCompanyName() . ' → ' . $status);
        $this->addFlash('success', 'Statut mis à jour.');
        return $this->redirectToRoute('app_lead_show', ['id' => $lead->getId()]);
    }

    #[Route('/search', name: 'app_lead_search')]
    public function search(): Response
    {
        return $this->render('lead/search.html.twig');
    }
}