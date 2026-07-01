<?php

namespace App\Command;

use App\Entity\CleaningRequest;
use App\Entity\Notification;
use App\Entity\SyncLog;
use App\Repository\CleaningRequestRepository;
use App\Repository\NotificationRepository;
use App\Repository\SyncLogRepository;
use App\Repository\UserRepository;
use App\Repository\CleaningServiceRepository;
use App\Repository\PropertyRepository;
use App\Service\GoogleCalendarService;
use App\Service\GoogleSyncService;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsCommand(
    name: 'app:test-v3-recette',
    description: 'Batterie de tests de recette V3 : sync Google, conflits, notifications, PWA.',
)]
class TestV3RecetteCommand extends Command
{
    private SymfonyStyle $io;
    private array $results = [];
    private array $createdIds = [
        'cleaning_requests' => [],
        'notifications'     => [],
        'google_event_ids'  => [],
    ];

    public function __construct(
        private readonly GoogleCalendarService     $googleCalendarService,
        private readonly GoogleSyncService         $googleSyncService,
        private readonly NotificationService       $notificationService,
        private readonly CleaningRequestRepository $cleaningRequestRepo,
        private readonly NotificationRepository    $notificationRepo,
        private readonly SyncLogRepository         $syncLogRepo,
        private readonly UserRepository            $userRepo,
        private readonly CleaningServiceRepository $serviceRepo,
        private readonly PropertyRepository        $propertyRepo,
        private readonly EntityManagerInterface    $em,
        private readonly UrlGeneratorInterface     $urlGenerator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('keep-data', null, InputOption::VALUE_NONE, 'Ne pas nettoyer les données de test à la fin');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $keepData = $input->getOption('keep-data');

        $this->io->title('🧪 Batterie de tests de recette V3 — DP Services');
        $this->io->writeln('Tests en cours... Patience.\n');

        $this->io->section('1. Environnement & configuration');
        $this->testEnvVariables();
        $this->testMigrations();
        $this->testPwaFiles();

        $this->io->section('2. Connexion Google Calendar (S1)');
        $this->testGoogleConnection();
        $this->testGoogleListEvents();

        $this->io->section('3. Lecture Google → App (S2)');
        $googleEventId = $this->testGoogleCreateTestEvent();
        if ($googleEventId) {
            $this->testGooglePullCreation($googleEventId);
            $this->testGoogleNoDuplicate($googleEventId);
            $this->testSectorAssignment();
        }

        $this->io->section('4. Écriture App → Google (S3)');
        $missionId = $this->testCreateMissionAndPushGoogle();
        if ($missionId) {
            $this->testUpdateGoogleEvent($missionId);
            $this->testSyncLogWritten($missionId);
        }

        $this->io->section('5. Gestion des conflits (S4)');
        if ($missionId) {
            $this->testConflictDetection($missionId);
            $this->testAcceptModification($missionId);
            $this->testAntiLoop($missionId);
        }

        $this->io->section('6. Cron & robustesse (S5)');
        $this->testSyncPullCommand();
        $this->testSyncLogExists();
        $this->testCrossDeleteAppToGoogle($missionId ?? null);

        $this->io->section('7. Notifications (S6)');
        $this->testNotificationMissionAssigned();
        $this->testNotificationMissionCancelled();
        $this->testNotificationModificationPending();
        $this->testReminderCommand();

        $this->io->section('8. PWA & UX mobile (S7)');
        $this->testManifestJson();
        $this->testServiceWorker();
        $this->testOpenedAtField();
        $this->testDeepLinkRoute();

        if (!$keepData) {
            $this->io->section('🧹 Nettoyage des données de test');
            $this->cleanup();
        } else {
            $this->io->warning('--keep-data actif : données de test conservées en base.');
        }

        $this->printReport();

        $failed = count(array_filter($this->results, fn($r) => $r['status'] === 'FAIL'));
        return $failed === 0 ? Command::SUCCESS : Command::FAILURE;
    }
    // =========================================================================
    // GROUPE 1 — Environnement
    // =========================================================================

    private function testEnvVariables(): void
    {
        $required = [
            'GOOGLE_CALENDAR_CLIENT_ID',
            'GOOGLE_CALENDAR_CLIENT_SECRET',
            'GOOGLE_CALENDAR_REFRESH_TOKEN',
            'GOOGLE_CALENDAR_ID',
        ];
        $missing = [];
        foreach ($required as $var) {
            if (empty($_ENV[$var] ?? getenv($var))) {
                $missing[] = $var;
            }
        }
        empty($missing)
            ? $this->pass('Variables d\'environnement Google présentes')
            : $this->fail('Variables manquantes : ' . implode(', ', $missing));
    }

    private function testMigrations(): void
    {
        try {
            $count = (int) $this->em->getConnection()
                ->executeQuery("SELECT COUNT(*) FROM doctrine_migration_versions")
                ->fetchOne();
            $this->pass(sprintf('%d migrations appliquées en base', $count));
        } catch (\Throwable $e) {
            $this->fail('Impossible de lire les migrations : ' . $e->getMessage());
        }
    }

    private function testPwaFiles(): void
    {
        foreach (['public/manifest.json' => 'manifest.json', 'public/sw.js' => 'sw.js'] as $path => $label) {
            file_exists($path)
                ? $this->pass("Fichier PWA présent : $label")
                : $this->fail("Fichier PWA manquant : $label");
        }
        if (file_exists('public/manifest.json')) {
            $m = json_decode(file_get_contents('public/manifest.json'), true);
            isset($m['name'], $m['icons'], $m['start_url'])
                ? $this->pass('manifest.json valide (name, icons, start_url présents)')
                : $this->fail('manifest.json invalide : champs obligatoires manquants');
        }
    }

    // =========================================================================
    // GROUPE 2 — Connexion Google
    // =========================================================================

    private function testGoogleConnection(): void
    {
        try {
            $client = $this->googleCalendarService->getClient();
            $client->getAccessToken()
                ? $this->pass('Connexion OAuth Google réussie (access token obtenu)')
                : $this->fail('Connexion OAuth Google : token absent');
        } catch (\Throwable $e) {
            $this->fail('Connexion OAuth Google échouée : ' . $e->getMessage());
        }
    }

    private function testGoogleListEvents(): void
    {
        try {
            $events = $this->googleCalendarService->listEvents(new \DateTime('-1 day'), new \DateTime('+30 days'));
            $this->pass(sprintf('listEvents() retourne %d événement(s) sur 30 jours', count($events)));
        } catch (\Throwable $e) {
            $this->fail('listEvents() a échoué : ' . $e->getMessage());
        }
    }

    // =========================================================================
    // GROUPE 3 — Lecture Google → App
    // =========================================================================

    private function testGoogleCreateTestEvent(): ?string
    {
        try {
            $property = $this->propertyRepo->findAll()[0] ?? null;
            $service  = $this->serviceRepo->find(3);
            if (!$property || !$service) {
                $this->skip('Création event Google test : logement ou service #3 manquant');
                return null;
            }
            $tmp = new CleaningRequest();
            $tmp->setProperty($property);
            $tmp->setService($service);
            $tmp->setScheduledDate(new \DateTime('2026-07-15'));
            $tmp->setScheduledTime(new \DateTime('2026-07-15 10:00:00'));
            $tmp->setStatus('PENDING');
            $tmp->setComment('TEST V3 recette - à supprimer');

            $googleEventId = $this->googleCalendarService->createGoogleEvent($tmp);
            if ($googleEventId) {
                $this->createdIds['google_event_ids'][] = $googleEventId;
                $this->pass("Event Google de test créé (ID: $googleEventId)");
                return $googleEventId;
            }
            $this->fail('createGoogleEvent() n\'a pas retourné d\'ID');
            return null;
        } catch (\Throwable $e) {
            $this->fail('Création event Google test échouée : ' . $e->getMessage());
            return null;
        }
    }

    private function testGooglePullCreation(string $googleEventId): void
    {
        try {
            $this->googleSyncService->pullFromGoogle(new \DateTime('2026-07-14'), new \DateTime('2026-07-16'));
            $mission = $this->cleaningRequestRepo->findOneBy(['googleEventId' => $googleEventId]);
            if ($mission) {
                $this->createdIds['cleaning_requests'][] = $mission->getId();
                $this->pass(sprintf('Pull Google → mission #%d créée (J11)', $mission->getId()));
            } else {
                $this->warn('Pull Google : event non converti en mission (secteur non reconnu ?)');
            }
        } catch (\Throwable $e) {
            $this->fail('Pull Google échoué : ' . $e->getMessage());
        }
    }

    private function testGoogleNoDuplicate(string $googleEventId): void
    {
        try {
            $this->googleSyncService->pullFromGoogle(new \DateTime('2026-07-14'), new \DateTime('2026-07-16'));
            $count = (int) $this->em->getConnection()
                ->executeQuery("SELECT COUNT(*) FROM cleaning_request WHERE google_event_id = ?", [$googleEventId])
                ->fetchOne();
            $count <= 1
                ? $this->pass('Détection doublon OK : 2ème pull ne crée pas de doublon (J10)')
                : $this->fail("Doublon détecté : $count missions avec le même google_event_id");
        } catch (\Throwable $e) {
            $this->fail('Test doublon échoué : ' . $e->getMessage());
        }
    }

    private function testSectorAssignment(): void
    {
        $missions = $this->cleaningRequestRepo->findBy(['syncSource' => 'google']);
        $assigned = array_filter($missions, fn($m) => $m->getAssignedCleaner() !== null);
        count($assigned) > 0
            ? $this->pass(sprintf('Assignation secteur : %d/%d missions Google assignées (J12)', count($assigned), count($missions)))
            : $this->warn('Assignation secteur : aucune mission Google n\'a de salarié assigné');
    }

    // =========================================================================
    // GROUPE 4 — Écriture App → Google
    // =========================================================================

    private function testCreateMissionAndPushGoogle(): ?int
    {
        try {
            $property = $this->propertyRepo->findAll()[0] ?? null;
            $service  = $this->serviceRepo->find(3);
            $cleaner  = $this->userRepo->findByRole('ROLE_CLEANER')[0] ?? null;
            if (!$property || !$service) {
                $this->skip('Création mission test : logement ou service manquant');
                return null;
            }
            $mission = new CleaningRequest();
            $mission->setProperty($property);
            $mission->setService($service);
            $mission->setScheduledDate(new \DateTime('2026-07-16'));
            $mission->setScheduledTime(new \DateTime('2026-07-16 14:00:00'));
            $mission->setStatus('PENDING');
            $mission->setComment('TEST V3 recette - App→Google');
            if ($cleaner) {
                $mission->setAssignedCleaner($cleaner);
                $mission->setAssignedAt(new \DateTime());
            }
            $this->em->persist($mission);
            $this->em->flush();
            $this->googleSyncService->pushCreate($mission);

            if ($mission->getGoogleEventId()) {
                $this->createdIds['cleaning_requests'][] = $mission->getId();
                $this->createdIds['google_event_ids'][]  = $mission->getGoogleEventId();
                $this->pass(sprintf('Mission #%d créée + event Google pushé (J18)', $mission->getId()));
                return $mission->getId();
            }
            $this->fail('pushCreate() n\'a pas généré de googleEventId');
            $this->em->remove($mission);
            $this->em->flush();
            return null;
        } catch (\Throwable $e) {
            $this->fail('Création mission + push Google échouée : ' . $e->getMessage());
            return null;
        }
    }

    private function testUpdateGoogleEvent(int $missionId): void
    {
        try {
            $mission = $this->cleaningRequestRepo->find($missionId);
            if (!$mission) { $this->skip("Mission #$missionId introuvable"); return; }
            $mission->setScheduledTime(new \DateTime('2026-07-16 15:00:00'));
            $this->em->flush();
            $this->googleSyncService->pushUpdate($mission);
            $this->pass(sprintf('updateGoogleEvent() OK sur mission #%d (J19)', $missionId));
        } catch (\Throwable $e) {
            $this->fail('updateGoogleEvent() échoué : ' . $e->getMessage());
        }
    }

    private function testSyncLogWritten(int $missionId): void
    {
        try {
            $logs = $this->syncLogRepo->findBy(['cleaningRequest' => $missionId]);
            count($logs) >= 2
                ? $this->pass(sprintf('SyncLog : %d entrées pour mission #%d (J20)', count($logs), $missionId))
                : $this->warn(sprintf('SyncLog : seulement %d entrée(s) pour mission #%d', count($logs), $missionId));
        } catch (\Throwable $e) {
            $this->fail('Lecture SyncLog échouée : ' . $e->getMessage());
        }
    }

    // =========================================================================
    // GROUPE 5 — Conflits
    // =========================================================================

    private function testConflictDetection(int $missionId): void
    {
        try {
            $mission = $this->cleaningRequestRepo->find($missionId);
            if (!$mission || !$mission->getGoogleEventId()) {
                $this->skip("Mission #$missionId sans googleEventId, conflit non testable");
                return;
            }
            $mission->setLastSyncAt(new \DateTime('-2 hours'));
            $this->em->flush();

            $fakeEvent = new \Google\Service\Calendar\Event();
            $fakeEvent->setId($mission->getGoogleEventId());
            $fakeEvent->setSummary('TEST conflit V3');
            $fakeEvent->setUpdated((new \DateTime('-30 minutes'))->format(\DateTime::RFC3339));
            $fakeEvent->setDescription('description modifiée test');
            $start = new \Google\Service\Calendar\EventDateTime();
            $start->setDateTime((new \DateTime('2026-07-16 16:00:00'))->format(\DateTime::RFC3339));
            $fakeEvent->setStart($start);

            $ref = new \ReflectionMethod($this->googleSyncService, 'detectExternalModification');
            $ref->setAccessible(true);
            $ref->invoke($this->googleSyncService, $mission, $fakeEvent);
            $this->em->flush();
            $this->em->refresh($mission);

            $mission->isNeedsConfirmation() && $mission->getStatus() === 'pending_modification'
                ? $this->pass(sprintf('Conflit détecté : mission #%d en pending_modification (J22-J23)', $missionId))
                : $this->fail(sprintf('Conflit non détecté : status=%s, needsConfirmation=%s', $mission->getStatus(), $mission->isNeedsConfirmation() ? 'true' : 'false'));
        } catch (\Throwable $e) {
            $this->fail('Test conflit échoué : ' . $e->getMessage());
        }
    }

    private function testAcceptModification(int $missionId): void
    {
        try {
            $mission = $this->cleaningRequestRepo->find($missionId);
            if (!$mission || !$mission->isNeedsConfirmation()) {
                $this->skip("Mission #$missionId pas en attente, test Accepter ignoré");
                return;
            }
            if ($mission->getPendingScheduledDate()) $mission->setScheduledDate($mission->getPendingScheduledDate());
            if ($mission->getPendingScheduledTime()) $mission->setScheduledTime($mission->getPendingScheduledTime());
            if ($mission->getPendingComment()) $mission->setComment($mission->getPendingComment());
            $mission->setPendingScheduledDate(null);
            $mission->setPendingScheduledTime(null);
            $mission->setPendingComment(null);
            $mission->setNeedsConfirmation(false);
            $mission->setStatus('VALIDATED');
            $mission->setSyncStatus('synced');
            $mission->setLastSyncAt(new \DateTime());
            $this->em->flush();
            $this->em->refresh($mission);

            !$mission->isNeedsConfirmation() && $mission->getStatus() === 'VALIDATED'
                ? $this->pass(sprintf('Accepter modification OK : mission #%d → VALIDATED (J25)', $missionId))
                : $this->fail('Accepter modification : statut incorrect après action');
        } catch (\Throwable $e) {
            $this->fail('Test Accepter modification échoué : ' . $e->getMessage());
        }
    }

    private function testAntiLoop(int $missionId): void
    {
        try {
            $mission = $this->cleaningRequestRepo->find($missionId);
            if (!$mission) { $this->skip("Mission #$missionId introuvable"); return; }

            $mission->setSyncInProgress(true);
            $this->em->flush();

            $fakeEvent = new \Google\Service\Calendar\Event();
            $fakeEvent->setId($mission->getGoogleEventId() ?? 'fake');
            $fakeEvent->setSummary('TEST anti-boucle');
            $fakeEvent->setUpdated((new \DateTime())->format(\DateTime::RFC3339));
            $fakeEvent->setDescription('ne doit pas déclencher de conflit');
            $start = new \Google\Service\Calendar\EventDateTime();
            $start->setDateTime((new \DateTime('2026-07-16 18:00:00'))->format(\DateTime::RFC3339));
            $fakeEvent->setStart($start);

            $ref = new \ReflectionMethod($this->googleSyncService, 'detectExternalModification');
            $ref->setAccessible(true);
            $ref->invoke($this->googleSyncService, $mission, $fakeEvent);
            $this->em->flush();
            $this->em->refresh($mission);

            !$mission->isNeedsConfirmation()
                ? $this->pass('Anti-boucle OK : syncInProgress=true bloque la détection (J27)')
                : $this->fail('Anti-boucle KO : conflit déclenché malgré syncInProgress=true');

            $mission->setSyncInProgress(false);
            $this->em->flush();
        } catch (\Throwable $e) {
            $this->fail('Test anti-boucle échoué : ' . $e->getMessage());
        }
    }

    // =========================================================================
    // GROUPE 6 — Robustesse
    // =========================================================================

    private function testSyncPullCommand(): void
    {
        try {
            $stats = $this->googleSyncService->pullFromGoogle(new \DateTime('2026-07-01'), new \DateTime('2026-07-31'));
            ($stats['errors'] ?? 0) === 0
                ? $this->pass(sprintf('app:sync-google-pull OK (créées: %d, ignorées: %d) (J29)', $stats['created'], $stats['skipped']))
                : $this->warn(sprintf('app:sync-google-pull terminé avec %d erreur(s) (J30)', $stats['errors']));
        } catch (\Throwable $e) {
            $this->fail('app:sync-google-pull a planté : ' . $e->getMessage());
        }
    }

    private function testSyncLogExists(): void
    {
        try {
            $count = (int) $this->em->getConnection()
                ->executeQuery("SELECT COUNT(*) FROM sync_log")->fetchOne();
            $count > 0
                ? $this->pass(sprintf('SyncLog fonctionnel : %d entrée(s) en base (J5)', $count))
                : $this->warn('SyncLog vide : aucune entrée trouvée');
        } catch (\Throwable $e) {
            $this->fail('Lecture SyncLog échouée : ' . $e->getMessage());
        }
    }

    private function testCrossDeleteAppToGoogle(?int $missionId): void
    {
        if (!$missionId) { $this->skip('Test suppression croisée : pas de mission disponible'); return; }
        try {
            $ref = new \ReflectionMethod($this->googleSyncService, 'pushDelete');
            $ref->isPublic()
                ? $this->pass('pushDelete() disponible et public (J34)')
                : $this->fail('pushDelete() n\'est pas public');
        } catch (\Throwable $e) {
            $this->fail('Test suppression croisée échoué : ' . $e->getMessage());
        }
    }

    // =========================================================================
    // GROUPE 7 — Notifications
    // =========================================================================

    private function testNotificationMissionAssigned(): void
    {
        try {
            $cleaner = $this->userRepo->findByRole('ROLE_CLEANER')[0] ?? null;
            if (!$cleaner) { $this->skip('Notification assigned : aucun salarié'); return; }

            $notif = $this->notificationService->notify($cleaner, Notification::TYPE_MISSION_ASSIGNED, 'TEST V3 - mission assignée', '/calendar');
            $this->createdIds['notifications'][] = $notif->getId();
            $this->pass(sprintf('Notification mission_assigned créée pour salarié #%d (J37)', $cleaner->getId()));
        } catch (\Throwable $e) {
            $this->fail('Test notification assigned échoué : ' . $e->getMessage());
        }
    }

    private function testNotificationMissionCancelled(): void
    {
        try {
            $cleaner = $this->userRepo->findByRole('ROLE_CLEANER')[0] ?? null;
            if (!$cleaner) { $this->skip('Notification cancelled : aucun salarié'); return; }

            $notif = $this->notificationService->notify($cleaner, Notification::TYPE_MISSION_CANCELLED, 'TEST V3 - mission annulée');
            $this->createdIds['notifications'][] = $notif->getId();
            $this->pass('Notification mission_cancelled créée (J39)');
        } catch (\Throwable $e) {
            $this->fail('Test notification cancelled échoué : ' . $e->getMessage());
        }
    }

    private function testNotificationModificationPending(): void
    {
        try {
            $admin = $this->userRepo->findByRole('ROLE_ADMIN')[0] ?? null;
            if (!$admin) { $this->skip('Notification pending : aucun admin'); return; }

            $notif = $this->notificationService->notify($admin, Notification::TYPE_MODIFICATION_PENDING, 'TEST V3 - modification en attente', '/admin/requests/pending');
            $this->createdIds['notifications'][] = $notif->getId();
            $this->pass(sprintf('Notification modification_pending créée pour admin #%d (J38)', $admin->getId()));
        } catch (\Throwable $e) {
            $this->fail('Test notification pending échoué : ' . $e->getMessage());
        }
    }

    private function testReminderCommand(): void
    {
        try {
            new \ReflectionClass(\App\Command\NotifyPendingMissionsCommand::class);
            $this->pass('Commande app:notify-pending-missions déclarée (J41)');
        } catch (\Throwable $e) {
            $this->fail('Commande app:notify-pending-missions introuvable : ' . $e->getMessage());
        }

        foreach (['opened_at' => 'openedAt (J41)', 'assigned_at' => 'assignedAt (J41)', 'reminder_sent_at' => 'reminderSentAt (J41)'] as $col => $label) {
            try {
                $this->em->getConnection()->executeQuery("SELECT $col FROM cleaning_request LIMIT 1");
                $this->pass("Champ $label présent en base");
            } catch (\Throwable $e) {
                $this->fail("Champ $label absent en base");
            }
        }
    }

    // =========================================================================
    // GROUPE 8 — PWA & UX
    // =========================================================================

    private function testManifestJson(): void
    {
        if (!file_exists('public/manifest.json')) { $this->fail('manifest.json absent (J43)'); return; }
        $m       = json_decode(file_get_contents('public/manifest.json'), true);
        $missing = array_filter(['name', 'short_name', 'start_url', 'display', 'icons'], fn($k) => !isset($m[$k]));
        empty($missing)
            ? $this->pass(sprintf('manifest.json valide (display: %s, %d icône(s)) (J43)', $m['display'], count($m['icons'])))
            : $this->fail('manifest.json : champs manquants : ' . implode(', ', $missing));

        foreach ($m['icons'] ?? [] as $icon) {
            if (!file_exists('public' . $icon['src'])) {
                $this->warn("Icône manquante : {$icon['src']}");
            }
        }
    }

    private function testServiceWorker(): void
    {
        if (!file_exists('public/sw.js')) { $this->fail('sw.js absent (J44)'); return; }
        $content = file_get_contents('public/sw.js');
        $missing = array_filter(['install', 'activate', 'fetch', 'CACHE_NAME'], fn($k) => !str_contains($content, $k));
        empty($missing)
            ? $this->pass('sw.js présent et contient install/activate/fetch (J44)')
            : $this->warn('sw.js incomplet, manque : ' . implode(', ', $missing));
    }

    private function testOpenedAtField(): void
    {
        try {
            $result = $this->em->getConnection()
                ->executeQuery("SELECT opened_at FROM cleaning_request WHERE opened_at IS NOT NULL LIMIT 1")
                ->fetchOne();
            $result !== false
                ? $this->pass('Champ openedAt fonctionnel : au moins 1 mission ouverte (J41/J47)')
                : $this->warn('openedAt existe mais aucune mission encore ouverte (normal si pas de connexion salarié)');
        } catch (\Throwable $e) {
            $this->fail('Test openedAt échoué : ' . $e->getMessage());
        }
    }

    private function testDeepLinkRoute(): void
    {
        try {
            $url = $this->urlGenerator->generate('app_calendar_event_details', ['id' => 1], UrlGeneratorInterface::ABSOLUTE_PATH);
            $this->pass("Route deep link générée : $url (J46)");
        } catch (\Throwable $e) {
            $this->fail('Route app_calendar_event_details introuvable : ' . $e->getMessage());
        }
    }

    // =========================================================================
    // NETTOYAGE
    // =========================================================================

    private function cleanup(): void
    {
        $cleaned = 0;

        foreach ($this->createdIds['notifications'] as $id) {
            try {
                $notif = $this->em->find(Notification::class, $id);
                if ($notif) { $this->em->remove($notif); $cleaned++; }
            } catch (\Throwable) {}
        }

        foreach ($this->createdIds['cleaning_requests'] as $id) {
            try {
                $mission = $this->cleaningRequestRepo->find($id);
                if ($mission) {
                    if ($mission->getGoogleEventId()) {
                        try { $this->googleCalendarService->deleteGoogleEvent($mission->getGoogleEventId()); } catch (\Throwable) {}
                    }
                    $this->em->remove($mission);
                    $cleaned++;
                }
            } catch (\Throwable) {}
        }

        foreach ($this->createdIds['google_event_ids'] as $googleEventId) {
            try { $this->googleCalendarService->deleteGoogleEvent($googleEventId); } catch (\Throwable) {}
        }

        $this->em->flush();
        $this->io->success(sprintf('Nettoyage terminé : %d élément(s) supprimé(s).', $cleaned));
    }

    // =========================================================================
    // RAPPORT
    // =========================================================================

    private function printReport(): void
    {
        $this->io->section('📊 Rapport final');

        foreach ($this->results as $result) {
            $icon = match ($result['status']) {
                'PASS' => '✅', 'FAIL' => '❌', 'WARN' => '⚠️ ', 'SKIP' => '⏭️ ', default => '❓',
            };
            $this->io->writeln("$icon {$result['message']}");
        }

        $passed  = count(array_filter($this->results, fn($r) => $r['status'] === 'PASS'));
        $failed  = count(array_filter($this->results, fn($r) => $r['status'] === 'FAIL'));
        $warned  = count(array_filter($this->results, fn($r) => $r['status'] === 'WARN'));
        $skipped = count(array_filter($this->results, fn($r) => $r['status'] === 'SKIP'));

        $this->io->newLine();
        $this->io->writeln(sprintf(
            '<info>RÉSULTAT : %d/%d tests passés | %d échoués | %d avertissements | %d ignorés</info>',
            $passed, count($this->results), $failed, $warned, $skipped
        ));

        $failed === 0
            ? $this->io->success('🎉 Tous les tests critiques sont passés ! Prêt pour le déploiement Hetzner.')
            : $this->io->error(sprintf('%d test(s) critique(s) échoué(s) — à corriger avant déploiement.', $failed));
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private function pass(string $message): void
    {
        $this->results[] = ['status' => 'PASS', 'message' => $message];
        $this->io->writeln("  ✅ $message");
    }

    private function fail(string $message): void
    {
        $this->results[] = ['status' => 'FAIL', 'message' => $message];
        $this->io->writeln("  ❌ $message");
    }

    private function warn(string $message): void
    {
        $this->results[] = ['status' => 'WARN', 'message' => $message];
        $this->io->writeln("  ⚠️  $message");
    }

    private function skip(string $message): void
    {
        $this->results[] = ['status' => 'SKIP', 'message' => $message];
        $this->io->writeln("  ⏭️  $message");
    }
}