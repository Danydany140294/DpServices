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
use Knp\Component\Pager\PaginatorInterface;
use App\Service\GooglePlacesService;

#[Route('/acquisition/leads')]
#[IsGranted('ROLE_ADMIN')]
class LeadController extends AbstractController
{
#[Route('', name: 'app_leads')]
public function index(LeadRepository $repo, LeadCategoryRepository $categoryRepo, Request $request, PaginatorInterface $paginator): Response
{
    $status = $request->query->get('status');
    $city = $request->query->get('city');
    $categoryId = $request->query->get('category');
    $scoreMin = $request->query->get('score_min');
    $scoreMin = is_numeric($scoreMin) ? $scoreMin : null;
    $followUp = $request->query->get('follow_up');

    $query = $repo->findWithFiltersQuery($status, $city, $categoryId, $scoreMin, $followUp);

    $leads = $paginator->paginate(
        $query,
        $request->query->getInt('page', 1),
        10
    );

    $categories = $categoryRepo->findAll();

    return $this->render('lead/index.html.twig', [
        'leads' => $leads,
        'categories' => $categories,
        'currentStatus' => $status,
        'currentCity' => $city,
        'currentCategory' => $categoryId,
        'currentScoreMin' => $scoreMin,
        'currentFollowUp' => $followUp,
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

    #[Route('/search', name: 'app_lead_search')]
    public function search(Request $request, GooglePlacesService $places): Response
    {
    $query = $request->query->get('query', '');
    $city = $request->query->get('city', '');
    $results = null;

    if ($query && $city) {
        $results = $places->searchPlaces($query, $city);
    }

    return $this->render('lead/search.html.twig', [
        'results' => $results,
        'query' => $query,
        'city' => $city,
    ]);
    }

    #[Route('/import', name: 'app_lead_import', methods: ['POST'])]
public function import(Request $request, EntityManagerInterface $em, LeadCategoryRepository $categoryRepo, ActivityLogService $logger): Response
{
    $name = $request->request->get('name');
    $address = $request->request->get('address');
    $rating = $request->request->get('rating');
    $reviews = $request->request->get('reviews');
    $city = $request->request->get('city');
    $query = $request->request->get('query');

    // Cherche la catégorie correspondante
    $categoryName = match(true) {
        str_contains($query, 'airbnb') => 'Conciergerie Airbnb',
        str_contains($query, 'locative') => 'Gestion locative',
        str_contains($query, 'immobilière') => 'Agence immobilière',
        default => 'Location saisonnière',
    };

    $category = $categoryRepo->findOneBy(['name' => $categoryName]);

    $lead = new Lead();
    $lead->setCompanyName($name);
    $lead->setCity($city);
    $lead->setSource('Google Places');
    $lead->setGoogleRating($rating ? (float) $rating : null);
    $lead->setGoogleReviews($reviews ? (int) $reviews : null);
    $lead->setCategory($category);

    // Calcul score automatique
    $score = 0;
    if ($category) $score += $category->getScoreBonus();
    if ($rating >= 4.5) $score += 10;
    if ($reviews >= 100) $score += 20;
    elseif ($reviews >= 50) $score += 10;
    $lead->setScore($score);

    $em->persist($lead);
    $em->flush();

    $logger->log('Lead importé', $name . ' — ' . $city);
    $this->addFlash('success', $name . ' importé avec succès. Score : ' . $score);

    return $this->redirectToRoute('app_lead_search', [
        'query' => $query,
        'city' => $city,
    ]);
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

    

    #[Route('/{id}/activity/new', name: 'app_lead_activity_new', methods: ['GET', 'POST'])]
public function newActivity(Lead $lead, Request $request, EntityManagerInterface $em, ActivityLogService $logger): Response
{
    $activity = new \App\Entity\LeadActivity();
    $activity->setLead($lead);

    $form = $this->createForm(\App\Form\LeadActivityType::class, $activity);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        // Changement de statut automatique (J12)
        $result = $activity->getResult();
        if ($activity->getType() === 'QUOTE') {
            $lead->setStatus('QUOTE_SENT');
        } elseif ($result === 'POSITIVE' && $lead->getStatus() === 'CONTACTED') {
            $lead->setStatus('DISCUSSION');
        } elseif ($result === 'NEGATIVE') {
            $lead->setStatus('LOST');
        } elseif ($result === 'CALLBACK') {
            $lead->setStatus('TO_FOLLOW_UP');
        } elseif ($lead->getStatus() === 'NEW') {
            $lead->setStatus('CONTACTED');
        }

        // Mise à jour de la date de relance
        if ($activity->getFollowUpDate()) {
            $lead->setNextFollowUp($activity->getFollowUpDate());
        }

        $em->persist($activity);
        $em->flush();

        $logger->log('Action lead', $lead->getCompanyName() . ' — ' . $activity->getType());
        $this->addFlash('success', 'Action enregistrée.');
        return $this->redirectToRoute('app_lead_show', ['id' => $lead->getId()]);
    }

    return $this->render('lead/activity_new.html.twig', [
        'lead' => $lead,
        'form' => $form->createView(),
    ]);
}

#[Route('/{id}/activity/{activityId}/delete', name: 'app_lead_activity_delete', methods: ['POST'])]
public function deleteActivity(Lead $lead, int $activityId, EntityManagerInterface $em): Response
{
    $activity = $em->getRepository(\App\Entity\LeadActivity::class)->find($activityId);
    if ($activity && $activity->getLead() === $lead) {
        $em->remove($activity);
        $em->flush();
        $this->addFlash('success', 'Action supprimée.');
    }
    return $this->redirectToRoute('app_lead_show', ['id' => $lead->getId()]);
}

#[Route('/{id}/notes', name: 'app_lead_notes', methods: ['POST'])]
public function updateNotes(Lead $lead, Request $request, EntityManagerInterface $em): Response
{
    $notes = $request->request->get('notes');
    $lead->setNotes($notes);
    $em->flush();
    $this->addFlash('success', 'Notes mises à jour.');
    return $this->redirectToRoute('app_lead_show', ['id' => $lead->getId()]);
}
}