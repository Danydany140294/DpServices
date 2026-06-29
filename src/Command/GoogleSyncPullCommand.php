<?php

namespace App\Command;

use App\Entity\SyncLog;
use App\Service\GoogleSyncService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sync-google-pull',
    description: 'Synchronise les événements Google Agenda vers des missions CleaningRequest (lecture seule, ne modifie pas Google).',
)]
class GoogleSyncPullCommand extends Command
{
    public function __construct(
        private readonly GoogleSyncService $googleSyncService,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('from', null, InputOption::VALUE_REQUIRED, 'Date de début (Y-m-d), active la détection de suppression (J34)')
            ->addOption('to', null, InputOption::VALUE_REQUIRED, 'Date de fin (Y-m-d), active la détection de suppression (J34)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Synchronisation Google Agenda -> Missions');

        $from = $input->getOption('from');
        $to = $input->getOption('to');

        $timeMin = $from !== null ? new \DateTime($from) : null;
        $timeMax = $to !== null ? new \DateTime($to . ' 23:59:59') : null;

        try {
            $stats = $this->googleSyncService->pullFromGoogle($timeMin, $timeMax);
        } catch (\Throwable $e) {
            $this->logger->error('GoogleSyncPullCommand: échec système de la synchronisation', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->logSystemFailure($e);

            $io->error('Échec système de la synchronisation : ' . $e->getMessage());
            $io->note('Cette erreur a été enregistrée dans les logs et dans le journal de synchronisation (SyncLog).');

            return Command::FAILURE;
        }

        if ($stats['errors'] > 0) {
            $io->warning(sprintf(
                'Synchronisation terminée avec %d erreur(s) ponctuelle(s) sur des événements individuels. Voir le journal de synchronisation pour le détail.',
                $stats['errors']
            ));
        } else {
            $io->success('Synchronisation terminée sans erreur.');
        }

        $io->table(
            ['Missions créées', 'Événements ignorés (déjà connus)', 'Missions supprimées', 'Erreurs'],
            [[$stats['created'], $stats['skipped'], $stats['deleted'] ?? 0, $stats['errors']]]
        );

        return Command::SUCCESS;
    }

    private function logSystemFailure(\Throwable $e): void
    {
        $log = new SyncLog();
        $log->setAction(SyncLog::ACTION_ERROR);
        $log->setSource(SyncLog::SOURCE_GOOGLE);
        $log->setMessage(sprintf(
            'Échec système de la synchronisation (app:sync-google-pull) : %s',
            $e->getMessage()
        ));

        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }
}