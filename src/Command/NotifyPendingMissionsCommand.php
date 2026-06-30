<?php

namespace App\Command;

use App\Repository\CleaningRequestRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * J41 : relance les salariés qui n'ont pas consulté une mission assignée
 * depuis plus de 6h. À exécuter via cron (ex: toutes les heures).
 */
#[AsCommand(
    name: 'app:notify-pending-missions',
    description: 'Relance les missions assignées non consultées depuis plus de 6h.',
)]
class NotifyPendingMissionsCommand extends Command
{
    private const HOURS_THRESHOLD = 6;

    public function __construct(
        private readonly CleaningRequestRepository $repo,
        private readonly NotificationService $notificationService,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $threshold = new \DateTime(sprintf('-%d hours', self::HOURS_THRESHOLD));

        $candidates = $this->repo->createQueryBuilder('cr')
            ->where('cr.assignedCleaner IS NOT NULL')
            ->andWhere('cr.openedAt IS NULL')
            ->andWhere('cr.assignedAt <= :threshold')
            ->andWhere('cr.reminderSentAt IS NULL')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getResult();

        $count = 0;
        foreach ($candidates as $cleaningRequest) {
            $this->notificationService->notifyMissionReminder(
                $cleaningRequest->getAssignedCleaner(),
                $cleaningRequest
            );

            $cleaningRequest->setReminderSentAt(new \DateTime());
            ++$count;
        }

        $this->em->flush();

        $io->success(sprintf('%d relance(s) envoyée(s).', $count));

        return Command::SUCCESS;
    }
}