<?php

namespace App\Command;

use App\Entity\SyncLog;
use App\Service\GoogleSyncService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
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
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    /**
     * J30 : robustesse face aux erreurs.
     *
     * Cette commande est destinée à tourner via cron (J29), sans supervision
     * humaine directe. Deux niveaux d'erreur sont distingués :
     *
     *   1. Erreur PONCTUELLE (un event Google problématique) : déjà gérée par
     *      GoogleSyncService::pullFromGoogle(), qui continue sur les autres
     *      events et remonte un compteur d'erreurs dans $stats['errors'].
     *
     *   2. Erreur SYSTÈME (connexion Google impossible, token invalide, panne
     *      réseau) : capturée ici. La commande retourne un code d'échec clair
     *      pour le système (utile pour une supervision externe basée sur le
     *      code de sortie), logue l'erreur dans les logs Symfony ET dans
     *      SyncLog (pour garder une trace consultable depuis l'admin, même
     *      si personne ne regarde les logs serveur ce jour-là).
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Synchronisation Google Agenda -> Missions');

        try {
            $stats = $this->googleSyncService->pullFromGoogle();
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
            ['Missions créées', 'Événements ignorés (déjà connus)', 'Erreurs'],
            [[$stats['created'], $stats['skipped'], $stats['errors']]]
        );

        // Code de sortie : SUCCESS même s'il y a eu des erreurs ponctuelles,
        // car celles-ci sont déjà tracées individuellement et ne remettent pas
        // en cause le bon déroulement global du cron. Seule une erreur SYSTÈME
        // (catch ci-dessus) doit faire échouer la commande pour une supervision
        // externe éventuelle (ex: alerte si le cron échoue plusieurs fois de suite).
        return Command::SUCCESS;
    }

    /**
     * Enregistre une erreur système (non liée à un event précis) dans SyncLog,
     * pour qu'elle soit visible depuis l'admin même sans accès aux logs serveur.
     */
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