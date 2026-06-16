<?php

namespace App\Controller;

use App\Entity\Devis;
use App\Entity\DevisLigne;
use App\Repository\LeadRepository;
use App\Repository\TarifPrestationRepository;
use App\Repository\DevisRepository;
use App\Service\DevisPdfService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/acquisition/devis')]
class DevisController extends AbstractController
{
    #[Route('/nouveau/{leadId}', name: 'devis_nouveau')]
    public function nouveau(
        int $leadId,
        Request $request,
        LeadRepository $leadRepo,
        TarifPrestationRepository $tarifRepo,
        EntityManagerInterface $em
    ): Response {
        $lead = $leadRepo->find($leadId);
        if (!$lead) {
            throw $this->createNotFoundException('Lead introuvable');
        }

        $tarifs = $tarifRepo->findBy([], ['categorie' => 'ASC', 'nom' => 'ASC']);

        if ($request->isMethod('POST')) {
            $devis = new Devis();
            $devis->setLead($lead);
            $devis->setStatus('brouillon');
            $devis->setCreatedAt(new \DateTimeImmutable());
            $devis->setNotes($request->request->get('notes'));
            $devis->setDiscount((float) $request->request->get('discount', 0) ?: null);

            $total = 0;
            $tarifIds = $request->request->all('tarif_id');
            $quantites = $request->request->all('quantite');

            foreach ($tarifIds as $i => $tarifId) {
                $qte = (int)($quantites[$i] ?? 1);
                if ($qte <= 0) continue;

                $tarif = $tarifRepo->find($tarifId);
                if (!$tarif) continue;

                $ligne = new DevisLigne();
                $ligne->setDevis($devis);
                $ligne->setTarifPrestation($tarif);
                $ligne->setQuantite($qte);
                $ligne->setPrixUnitaire($tarif->getPrix());
                $ligne->setDescription($tarif->getDescription());

                $total += $tarif->getPrix() * $qte;
                $devis->addDevisLigne($ligne);
                $em->persist($ligne);
            }

            $devis->setTotal($total);
            $em->persist($devis);
            $em->flush();

            $this->addFlash('success', 'Devis créé avec succès.');
            return $this->redirectToRoute('devis_apercu', ['id' => $devis->getId()]);
        }

        return $this->render('devis/nouveau.html.twig', [
            'lead' => $lead,
            'tarifs' => $tarifs,
        ]);
    }

    #[Route('/apercu/{id}', name: 'devis_apercu')]
    public function apercu(Devis $devis): Response
    {
        return $this->render('devis/apercu.html.twig', [
            'devis' => $devis,
        ]);
    }

    #[Route('/pdf/{id}', name: 'devis_pdf')]
    public function pdf(Devis $devis, DevisPdfService $pdfService): Response
    {
        $pdfContent = $pdfService->generer($devis);

        return new Response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="devis-' . $devis->getId() . '.pdf"',
        ]);
    }

    #[Route('/liste/{leadId}', name: 'devis_liste')]
    public function liste(int $leadId, LeadRepository $leadRepo): Response
    {
        $lead = $leadRepo->find($leadId);
        if (!$lead) {
            throw $this->createNotFoundException('Lead introuvable');
        }

        return $this->render('devis/liste.html.twig', [
            'lead' => $lead,
            'devis' => $lead->getDevis(),
        ]);
    }
}