<?php

namespace App\Command;

use App\Service\GoogleCalendarService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:google-test',
    description: 'Teste la connexion Google Calendar en listant les prochains événements.',
)]
class GoogleTestCommand extends Command
{
    public function __construct(
        private readonly GoogleCalendarService $googleCalendarService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $events = $this->googleCalendarService->testConnection(5);
        } catch (\Throwable $e) {
            $io->error('Échec de la connexion : ' . $e->getMessage());

            return Command::FAILURE;
        }

        if (empty($events)) {
            $io->success('Connexion réussie ! Aucun événement à venir dans ce calendrier.');

            return Command::SUCCESS;
        }

        $io->success(sprintf('Connexion réussie ! %d événement(s) trouvé(s) :', count($events)));

        $io->table(
            ['ID', 'Titre', 'Début'],
            array_map(fn ($e) => [$e['id'], $e['summary'], $e['start']], $events)
        );

        return Command::SUCCESS;
    }
}