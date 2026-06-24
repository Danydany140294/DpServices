<?php

namespace App\Command;

use App\Service\GoogleCalendarService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:google-auth',
    description: 'Génère le refresh token Google Calendar (à exécuter une seule fois, à la configuration initiale).',
)]
class GoogleAuthCommand extends Command
{
    public function __construct(
        private readonly GoogleCalendarService $googleCalendarService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $authUrl = $this->googleCalendarService->createAuthUrl();

        $io->title('Authentification Google Calendar');
        $io->writeln('1. Ouvrez ce lien dans votre navigateur (connecté avec le compte admin/entreprise) :');
        $io->writeln('');
        $io->writeln($authUrl);
        $io->writeln('');
        $io->writeln('2. Autorisez l\'application.');
        $io->writeln('3. Google va vous rediriger vers une URL qui ne fonctionnera pas (normal, pas de serveur derrière) :');
        $io->writeln('   http://localhost:8000/google/callback?code=XXXXX&scope=...');
        $io->writeln('4. Copiez uniquement la valeur du paramètre "code" dans cette URL.');
        $io->writeln('');

        $authCode = $io->ask('Collez ici le code récupéré');

        if (empty($authCode)) {
            $io->error('Aucun code fourni.');

            return Command::FAILURE;
        }

        try {
            $token = $this->googleCalendarService->fetchTokenWithAuthCode($authCode);
        } catch (\Throwable $e) {
            $io->error('Erreur lors de l\'échange du code : ' . $e->getMessage());

            return Command::FAILURE;
        }

        if (!isset($token['refresh_token'])) {
            $io->error(
                'Aucun refresh_token reçu. Cela arrive si vous avez déjà autorisé cette application '
                . 'précédemment. Allez sur https://myaccount.google.com/permissions, révoquez l\'accès '
                . 'à "DP Services", puis relancez cette commande.'
            );

            return Command::FAILURE;
        }

        $io->success('Refresh token obtenu avec succès !');
        $io->writeln('Ajoutez cette ligne dans votre fichier .env.local :');
        $io->writeln('');
        $io->writeln(sprintf('GOOGLE_CALENDAR_REFRESH_TOKEN=%s', $token['refresh_token']));
        $io->writeln('');

        return Command::SUCCESS;
    }
}