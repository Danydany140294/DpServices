<?php

namespace App\Command;

use App\Service\GoogleSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
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
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Synchronisation Google Agenda -> Missions');

        try {
            $stats = $this->googleSyncService->pullFromGoogle();
        } catch (\Throwable $e) {
            $io->error('Échec de la synchronisation : ' . $e->getMessage());

            return Command::FAILURE;
        }

        $io->success('Synchronisation terminée.');

        $io->table(
            ['Missions créées', 'Événements ignorés (déjà connus)', 'Erreurs'],
            [[$stats['created'], $stats['skipped'], $stats['errors']]]
        );

        return Command::SUCCESS;
    }
}