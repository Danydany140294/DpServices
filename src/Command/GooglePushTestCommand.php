<?php

namespace App\Command;

use App\Repository\CleaningRequestRepository;
use App\Service\GoogleCalendarService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Doctrine\ORM\EntityManagerInterface;

#[AsCommand(
    name: 'app:google-push-test',
    description: 'Teste la création/modification/suppression d\'un événement Google à partir d\'une mission existante.',
)]
class GooglePushTestCommand extends Command
{
    public function __construct(
        private readonly GoogleCalendarService $googleCalendarService,
        private readonly CleaningRequestRepository $cleaningRequestRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('id', InputArgument::REQUIRED, 'ID de la CleaningRequest à utiliser pour le test')
            ->addArgument('action', InputArgument::OPTIONAL, 'create | update | delete', 'create');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $id = (int) $input->getArgument('id');
        $action = $input->getArgument('action');

        $cleaningRequest = $this->cleaningRequestRepository->find($id);
        if ($cleaningRequest === null) {
            $io->error(sprintf('Aucune CleaningRequest trouvée avec l\'id %d.', $id));

            return Command::FAILURE;
        }

        try {
            switch ($action) {
                case 'create':
                    $googleEventId = $this->googleCalendarService->createGoogleEvent($cleaningRequest);
                    $cleaningRequest->setGoogleEventId($googleEventId);
                    $cleaningRequest->setSyncSource('app');
                    $cleaningRequest->setSyncStatus('synced');
                    $cleaningRequest->setLastSyncAt(new \DateTime());
                    $this->entityManager->flush();
                    $io->success(sprintf('Événement Google créé avec succès. ID : %s', $googleEventId));
                    break;

                case 'update':
                    $this->googleCalendarService->updateGoogleEvent($cleaningRequest);
                    $cleaningRequest->setLastSyncAt(new \DateTime());
                    $this->entityManager->flush();
                    $io->success('Événement Google mis à jour avec succès.');
                    break;

                case 'delete':
                    $googleEventId = $cleaningRequest->getGoogleEventId();
                    if (empty($googleEventId)) {
                        $io->error('Cette mission n\'a pas de googleEventId, rien à supprimer.');

                        return Command::FAILURE;
                    }
                    $this->googleCalendarService->deleteGoogleEvent($googleEventId);
                    $cleaningRequest->setGoogleEventId(null);
                    $this->entityManager->flush();
                    $io->success('Événement Google supprimé avec succès.');
                    break;

                default:
                    $io->error('Action inconnue. Utilisez : create, update ou delete.');

                    return Command::FAILURE;
            }
        } catch (\Throwable $e) {
            $io->error('Échec : ' . $e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}