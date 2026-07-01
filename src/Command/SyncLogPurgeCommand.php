<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sync-log-purge',
    description: 'Supprime les anciens SyncLog (défaut : entrées de plus de 30 jours).',
)]
class SyncLogPurgeCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('days', 'd', InputOption::VALUE_REQUIRED, 'Nombre de jours à conserver', 30);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = (int) $input->getOption('days');
        $before = new \DateTime("-{$days} days");

        $deleted = $this->entityManager->createQueryBuilder()
            ->delete(\App\Entity\SyncLog::class, 's')
            ->where('s.createdAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();

        $this->entityManager->flush();

        $io->success(sprintf('%d SyncLog supprimé(s) (antérieurs à %d jours).', $deleted, $days));

        return Command::SUCCESS;
    }
}