<?php

namespace App\Controller;

use App\Entity\LeadActivity;
use App\Repository\LeadRepository;
use App\Service\LeadEmailService;
use App\Service\LeadSmsService;
use App\Service\ActivityLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/acquisition/campaign')]
#[IsGranted('ROLE_ADMIN')]
class LeadCampaignController extends AbstractController
{
    #[Route('/email/{id}', name: 'app_lead_email')]
    public function sendEmail(
        int $id,
        Request $request,
        LeadRepository $repo,
        LeadEmailService $emailService,
        EntityManagerInterface $em,
        ActivityLogService $logger
    ): Response {
        $lead = $repo->find($id);

        if ($request->isMethod('POST')) {
            $subject = $request->request->get('subject');
            $body    = $request->request->get('body');

            if ($request->request->get('confirmed')) {
                try {
                    $emailService->sendProspectionEmail($lead, $subject, $body);

                    $activity = new LeadActivity();
                    $activity->setLead($lead);
                    $activity->setType('EMAIL');
                    $activity->setResult('sent');
                    $activity->setNote('Email envoyé : ' . $subject);
                    $em->persist($activity);

                    $lead->setStatus('CONTACTED');
                    $em->flush();

                    $logger->log('Email envoyé', $lead->getCompanyName());
                    $this->addFlash('success', 'Email envoyé à ' . $lead->getCompanyName());
                    return $this->redirectToRoute('app_lead_show', ['id' => $lead->getId()]);
                } catch (\Throwable $e) {
                    $this->addFlash('error', 'Erreur : ' . $e->getMessage());
                }
            }

            return $this->render('lead_campaign/email_confirm.html.twig', [
                'lead'    => $lead,
                'subject' => $subject,
                'body'    => $body,
            ]);
        }

        return $this->render('lead_campaign/email.html.twig', ['lead' => $lead]);
    }

    #[Route('/sms/{id}', name: 'app_lead_sms')]
    public function sendSms(
        int $id,
        Request $request,
        LeadRepository $repo,
        LeadSmsService $smsService,
        EntityManagerInterface $em,
        ActivityLogService $logger
    ): Response {
        $lead = $repo->find($id);

        if ($request->isMethod('POST')) {
            $message = $request->request->get('message');

            if ($request->request->get('confirmed')) {
                try {
                    $smsService->sendSms($lead, $message);

                    $activity = new LeadActivity();
                    $activity->setLead($lead);
                    $activity->setType('SMS');
                    $activity->setResult('sent');
                    $activity->setNote('SMS envoyé');
                    $em->persist($activity);

                    $lead->setStatus('CONTACTED');
                    $em->flush();

                    $logger->log('SMS envoyé', $lead->getCompanyName());
                    $this->addFlash('success', 'SMS envoyé à ' . $lead->getCompanyName());
                    return $this->redirectToRoute('app_lead_show', ['id' => $lead->getId()]);
                } catch (\Throwable $e) {
                    $this->addFlash('error', 'Erreur : ' . $e->getMessage());
                }
            }

            return $this->render('lead_campaign/sms_confirm.html.twig', [
                'lead'    => $lead,
                'message' => $message,
            ]);
        }

        return $this->render('lead_campaign/sms.html.twig', ['lead' => $lead]);
    }

    #[Route('/group-email', name: 'app_lead_group_email')]
    public function groupEmail(
        Request $request,
        LeadRepository $repo,
        LeadEmailService $emailService,
        ActivityLogService $logger,
        EntityManagerInterface $em
    ): Response {
        $leads = $repo->findAll();

        if ($request->isMethod('POST')) {
            $subject = $request->request->get('subject');
            $body    = $request->request->get('body');
            $leadIds = $request->request->all('lead_ids');

            if ($request->request->get('confirmed') && !empty($leadIds)) {
                $selectedLeads = $repo->findBy(['id' => $leadIds]);
                $sent = $emailService->sendGroupEmail($selectedLeads, $subject, $body);

                foreach ($selectedLeads as $lead) {
                    $activity = new LeadActivity();
                    $activity->setLead($lead);
                    $activity->setType('EMAIL');
                    $activity->setResult('sent');
                    $activity->setNote('Email groupé : ' . $subject);
                    $em->persist($activity);
                    $lead->setStatus('CONTACTED');
                }
                $em->flush();

                $logger->log('Email groupé envoyé', $sent . ' emails envoyés');
                $this->addFlash('success', $sent . ' emails envoyés avec succès.');
                return $this->redirectToRoute('app_leads');
            }

            if ($request->request->get('preview')) {
                $leadIds = $request->request->all('lead_ids');
                return $this->render('lead_campaign/group_email_confirm.html.twig', [
                    'leads'   => $repo->findBy(['id' => $leadIds]),
                    'subject' => $subject,
                    'body'    => $body,
                    'leadIds' => $leadIds,
                ]);
            }
        }

        return $this->render('lead_campaign/group_email.html.twig', ['leads' => $leads]);
    }
}