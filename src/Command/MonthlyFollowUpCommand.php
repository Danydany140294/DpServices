<?php

namespace App\Command;

use App\Repository\LeadRepository;
use App\Service\LeadEmailService;
use App\Service\ActivityLogService;
use App\Entity\LeadActivity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:monthly-follow-up', description: 'Relance mensuelle automatique des prospects à relancer')]
class MonthlyFollowUpCommand extends Command
{
    public function __construct(
        private LeadRepository $leadRepo,
        private LeadEmailService $emailService,
        private EntityManagerInterface $em,
        private ActivityLogService $logger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $leads = $this->leadRepo->findBy(['status' => 'TO_FOLLOW_UP']);

        $output->writeln(count($leads) . ' prospect(s) à relancer trouvé(s).');

        $sent = 0;
        foreach ($leads as $lead) {
            if (!$lead->getEmail()) {
                continue;
            }

            $subject = 'DP Services — Toujours intéressé(e) ?';
            $body = "Bonjour,\n\nNous revenons vers vous concernant nos services de ménage Airbnb à " . $lead->getCity() . ".\n\nÊtes-vous toujours intéressé(e) par un devis ou souhaitez-vous qu'on échange à nouveau ?\n\nCordialement,\nL'équipe DP Services";

            try {
                $this->emailService->sendProspectionEmail($lead, $subject, $body);

                $activity = new LeadActivity();
                $activity->setLead($lead);
                $activity->setType('EMAIL');
                $activity->setResult('sent');
                $activity->setNote('Relance mensuelle automatique');
                $this->em->persist($activity);

                $sent++;
            } catch (\Throwable $e) {
                $output->writeln('Erreur pour ' . $lead->getCompanyName() . ' : ' . $e->getMessage());
            }
        }

        $this->em->flush();
        $this->logger->log('Relance mensuelle', $sent . ' email(s) envoyé(s)');
        $output->writeln('✅ ' . $sent . ' email(s) de relance envoyé(s).');

        return Command::SUCCESS;
    }
}