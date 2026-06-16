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
use App\Service\ScoringService;
use App\Service\MistralService;


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
public function search(Request $request, GooglePlacesService $places, LeadRepository $repo): Response
{
    $query       = $request->query->get('query', '');
    $city        = $request->query->get('city', '');
    $ignoreKnown    = $request->query->getBoolean('ignore_known');
    $ignoreClients  = $request->query->getBoolean('ignore_clients');
    $ignoreLost     = $request->query->getBoolean('ignore_lost');
    $results     = null;
    $skipped     = 0;

    if ($query && $city) {
        $raw = $places->searchPlaces($query, $city);

        // J27 — Récupère tous les noms existants en base (normalisés)
        $existingLeads = $repo->findAll();
        $existingNames = array_map(
            fn(Lead $l) => $this->normalizeName($l->getCompanyName()),
            $existingLeads
        );

        $results = [];
        foreach ($raw as $place) {
            $normalized = $this->normalizeName($place['name']);
            $isDuplicate = in_array($normalized, $existingNames);

            // J27 — Marque le doublon
            $place['duplicate'] = $isDuplicate;

            // J25 — Filtres
            if ($isDuplicate) {
                $lead = $this->findLeadByName($existingLeads, $normalized);
                $status = $lead?->getStatus();

                $skip = false;
                if ($ignoreKnown)   $skip = true;
                if ($ignoreClients  && $status === 'CLIENT')   $skip = true;
                if ($ignoreLost     && $status === 'LOST')     $skip = true;

                if ($skip) { $skipped++; continue; }
            }

            $results[] = $place;
        }
    }

    return $this->render('lead/search.html.twig', [
        'results'        => $results,
        'query'          => $query,
        'city'           => $city,
        'skipped'        => $skipped,
        'ignoreKnown'    => $ignoreKnown,
        'ignoreClients'  => $ignoreClients,
        'ignoreLost'     => $ignoreLost,
    ]);
}

private function normalizeName(string $name): string
{
    return strtolower(trim(preg_replace('/\s+/', ' ', $name)));
}

private function findLeadByName(array $leads, string $normalized): ?Lead
{
    foreach ($leads as $lead) {
        if ($this->normalizeName($lead->getCompanyName()) === $normalized) {
            return $lead;
        }
    }
    return null;
}



    #[Route('/import', name: 'app_lead_import', methods: ['POST'])]
public function import(Request $request, EntityManagerInterface $em, LeadCategoryRepository $categoryRepo, ActivityLogService $logger,ScoringService $scoring): Response
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
    str_contains($query, 'hôtellerie') => 'Hôtellerie',
    str_contains($query, 'immobiliers') => 'Services immobiliers',
    str_contains($query, 'professionnel') => 'Ménage professionnel',
    str_contains($query, 'particulier') => 'Ménage particulier',
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
    $lead->setScore($scoring->calculate($lead));

    $em->persist($lead);
    $em->flush();

    $logger->log('Lead importé', $name . ' — ' . $city);
    $this->addFlash('success', $name . ' importé avec succès. Score : ' . $lead->getScore());
    return $this->redirectToRoute('app_lead_search', [
        'query' => $query,
        'city' => $city,
    ]);
}
#[Route('/rescore-all', name: 'app_leads_rescore_all', methods: ['POST'])]
public function rescoreAll(LeadRepository $repo, EntityManagerInterface $em, ScoringService $scoring, ActivityLogService $logger): Response
{
    $leads = $repo->findAll();
    $count = 0;
    foreach ($leads as $lead) {
        $lead->setScore($scoring->calculate($lead));
        $count++;
    }
    $em->flush();
    $logger->log('Recalcul global scores', $count . ' prospects mis à jour');
    $this->addFlash('success', $count . ' scores recalculés.');
    return $this->redirectToRoute('app_leads');
}

    #[Route('/{id}/summarize', name: 'app_lead_summarize', methods: ['POST'])]
public function summarize(Lead $lead, MistralService $mistral): Response
{
    $category  = $lead->getCategory()?->getName() ?? 'inconnue';
    $city      = $lead->getCity() ?? 'inconnue';
    $score     = $lead->getScore();
    $status    = $lead->getStatus();
    $activities = $lead->getLeadActivities()->count();

    $prompt = "Voici un prospect commercial : entreprise '$category' à $city, score $score/100, statut $status, $activities action(s) enregistrée(s). Fais une synthèse en 3 lignes maximum : qui est ce prospect, où il en est, et quel est ton conseil pour la prochaine action commerciale. Sois direct et pratique.";

    $summary = $mistral->generate($prompt);

    $this->addFlash('ai_summary', $summary);
    return $this->redirectToRoute('app_lead_show', ['id' => $lead->getId()]);
}

    #[Route('/{id}/ai-suggest', name: 'app_lead_ai_suggest', methods: ['POST'])]
public function aiSuggest(Lead $lead, MistralService $mistral): Response
{
    $category = $lead->getCategory()?->getName() ?? 'conciergerie';
    $city     = $lead->getCity() ?? 'votre ville';

    $prompt = "Pour une entreprise de type '$category' à $city, quelles prestations de ménage Airbnb recommandes-tu parmi cette liste : Ménage Standard ≤45m², Option Linge, Entretien canapé/Matelas, Ménage approfondi, Check-in/check-out, Stock consommables, Main d'œuvre ménage. Réponds avec une liste courte et une phrase d'explication pour chaque prestation recommandée.";

    $suggestions = $mistral->generate($prompt);

    $this->addFlash('ai_summary', $suggestions);
    return $this->redirectToRoute('devis_nouveau', ['leadId' => $lead->getId()]);
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