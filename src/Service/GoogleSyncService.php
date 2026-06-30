<?php

namespace App\Service;

use App\Entity\CleaningRequest;
use App\Entity\CleaningService;
use App\Entity\Property;
use App\Entity\SyncLog;
use App\Repository\CleaningRequestRepository;
use App\Repository\CleaningServiceRepository;
use App\Repository\PropertyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Google\Service\Calendar\Event as GoogleEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Service de synchronisation Google Calendar -> Symfony (sens "pull").
 *
 * Transforme les événements Google Agenda en missions CleaningRequest,
 * avec détection des doublons (via googleEventId) et affectation automatique
 * du salarié selon le secteur géographique du bien.
 *
 * Règle V3 : Google Agenda n'est jamais la source de vérité. Cette méthode
 * ne fait que CRÉER de nouvelles missions à partir de nouveaux événements.
 *
 * J37 : si un salarié est affecté automatiquement, il est notifié (in-app +
 * SMS) exactement comme pour une création manuelle côté admin.
 */
class GoogleSyncService
{
    private const DEFAULT_SERVICE_ID = 3; // "Ménage simple"

    public function __construct(
        private readonly GoogleCalendarService $googleCalendarService,
        private readonly SectorAssignmentService $sectorAssignmentService,
        private readonly CleaningRequestRepository $cleaningRequestRepository,
        private readonly PropertyRepository $propertyRepository,
        private readonly CleaningServiceRepository $cleaningServiceRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly NotificationService $notificationService,
        private readonly \Symfony\Component\Routing\Generator\UrlGeneratorInterface $urlGenerator,
        private readonly \App\Repository\UserRepository $userRepository,
    ) {
    }
    public function pullFromGoogle(?\DateTimeInterface $timeMin = null, ?\DateTimeInterface $timeMax = null): array
    {
        $stats = ['created' => 0, 'skipped' => 0, 'errors' => 0, 'deleted' => 0];

        $defaultService = $this->cleaningServiceRepository->find(self::DEFAULT_SERVICE_ID);
        if ($defaultService === null) {
            throw new \RuntimeException(sprintf(
                'CleaningService par défaut (id=%d) introuvable en base. Vérifiez la table cleaning_service.',
                self::DEFAULT_SERVICE_ID
            ));
        }

        $events = $this->googleCalendarService->listEvents($timeMin, $timeMax);

        $seenGoogleEventIds = [];

        foreach ($events as $event) {
            $seenGoogleEventIds[] = $event->getId();

            try {
                $result = $this->processEvent($event, $defaultService);

                if ($result === null) {
                    ++$stats['skipped'];
                } else {
                    ++$stats['created'];
                }
            } catch (\Throwable $e) {
                ++$stats['errors'];
                $this->logSyncAction(
                    null,
                    SyncLog::ACTION_ERROR,
                    SyncLog::SOURCE_GOOGLE,
                    sprintf('Erreur lors du traitement de l\'event %s : %s', $event->getId(), $e->getMessage())
                );
                $this->logger->error('GoogleSyncService: erreur de traitement event', [
                    'google_event_id' => $event->getId(),
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        if ($timeMin !== null && $timeMax !== null) {
            $stats['deleted'] = $this->detectAndDeleteRemovedEvents($timeMin, $timeMax, $seenGoogleEventIds);
        }

        $this->entityManager->flush();

        return $stats;
    }

    private function processEvent(GoogleEvent $event, CleaningService $defaultService): ?CleaningRequest
    {
        $googleEventId = $event->getId();

        $existing = $this->cleaningRequestRepository->findOneBy(['googleEventId' => $googleEventId]);

        if ($existing !== null) {
            $this->detectExternalModification($existing, $event);
            return null;
        }

        $cleaningRequest = $this->googleEventToMission($event, $defaultService);

        if ($cleaningRequest === null) {
            $this->logSyncAction(
                null,
                SyncLog::ACTION_ERROR,
                SyncLog::SOURCE_GOOGLE,
                sprintf('Event "%s" (%s) ignoré : aucun secteur/bien correspondant trouvé', $event->getSummary(), $googleEventId)
            );

            return null;
        }

        $this->entityManager->persist($cleaningRequest);
        $this->logSyncAction($cleaningRequest, SyncLog::ACTION_CREATE, SyncLog::SOURCE_GOOGLE, sprintf(
            'Mission créée depuis l\'event Google "%s" (%s)',
            $event->getSummary(),
            $googleEventId
        ));

        // J37 : notifier le salarié (in-app + SMS) si une affectation automatique a eu lieu.
        if ($cleaningRequest->getAssignedCleaner() !== null) {
            $this->notificationService->notifyMissionAssigned(
                $cleaningRequest->getAssignedCleaner(),
                $cleaningRequest,
                $this->urlGenerator->generate('app_calendar')
            );
        }

        return $cleaningRequest;
    }

    private function detectExternalModification(CleaningRequest $cleaningRequest, GoogleEvent $event): void
    {
        if ($cleaningRequest->isSyncInProgress()) {
            return;
        }

        $googleUpdated = $event->getUpdated();
        $lastSyncAt = $cleaningRequest->getLastSyncAt();

        if ($googleUpdated !== null && $lastSyncAt !== null) {
            $googleUpdatedAt = new \DateTime($googleUpdated);
            if ($googleUpdatedAt <= $lastSyncAt) {
                return;
            }
        }

        if ($cleaningRequest->isNeedsConfirmation()) {
            return;
        }

        $start = $event->getStart();
        $startDateTime = $start->getDateTime() ?? $start->getDate();

        if ($startDateTime === null) {
            return;
        }

        $newDateTime = new \DateTime($startDateTime);

        $sameDate = $cleaningRequest->getScheduledDate()?->format('Y-m-d') === $newDateTime->format('Y-m-d');
        $sameTime = $cleaningRequest->getScheduledTime()?->format('H:i') === $newDateTime->format('H:i');
        $sameComment = ($cleaningRequest->getComment() ?? '') === ($event->getDescription() ?? '');

        if ($sameDate && $sameTime && $sameComment) {
            return;
        }

        $cleaningRequest->setPendingScheduledDate(clone $newDateTime);
        $cleaningRequest->setPendingScheduledTime(clone $newDateTime);
        $cleaningRequest->setPendingComment($event->getDescription());
        $cleaningRequest->setNeedsConfirmation(true);
        $cleaningRequest->setStatus('pending_modification');

        $this->logSyncAction(
            $cleaningRequest,
            SyncLog::ACTION_UPDATE,
            SyncLog::SOURCE_GOOGLE,
            sprintf(
                'Modification détectée hors admin pour la mission #%d (event "%s") : en attente de confirmation',
                $cleaningRequest->getId(),
                $event->getSummary()
            )
        );

        $this->notifyAdmins($cleaningRequest);
    }

    /**
     * Notifie tous les administrateurs qu'une modification Google attend
     * leur confirmation (J38).
     */
    private function notifyAdmins(CleaningRequest $cleaningRequest): void
    {
        $admins = $this->userRepository->findByRole('ROLE_ADMIN');

        foreach ($admins as $admin) {
            $this->notificationService->notify(
                $admin,
                \App\Entity\Notification::TYPE_MODIFICATION_PENDING,
                sprintf(
                    'Modification Google en attente : %s (mission #%d)',
                    $cleaningRequest->getProperty()->getName(),
                    $cleaningRequest->getId()
                ),
                $this->urlGenerator->generate('app_requests_pending')
            );
        }
    }

    public function googleEventToMission(GoogleEvent $event, CleaningService $defaultService): ?CleaningRequest
    {
        $property = $this->matchProperty($event);

        if ($property === null) {
            return null;
        }

        $start = $event->getStart();
        $startDateTime = $start->getDateTime() ?? $start->getDate();

        if ($startDateTime === null) {
            return null;
        }

        $dateTime = new \DateTime($startDateTime);

        $cleaningRequest = new CleaningRequest();
        $cleaningRequest->setProperty($property);
        $cleaningRequest->setService($defaultService);
        $cleaningRequest->setScheduledDate((clone $dateTime));
        $cleaningRequest->setScheduledTime((clone $dateTime));
        $cleaningRequest->setComment($event->getDescription());
        $cleaningRequest->setStatus('PENDING');
        $cleaningRequest->setGoogleEventId($event->getId());
        $cleaningRequest->setSyncSource(SyncLog::SOURCE_GOOGLE);
        $cleaningRequest->setSyncStatus('synced');
        $cleaningRequest->setLastSyncAt(new \DateTime());

        $cleaner = $this->sectorAssignmentService->findCleanerForProperty($property);
        if ($cleaner !== null) {
            $cleaningRequest->setAssignedCleaner($cleaner);
            $cleaningRequest->setAssignedAt(new \DateTime());
        }

        return $cleaningRequest;
    }

    public function pushCreate(CleaningRequest $cleaningRequest): void
    {
        try {
            $googleEventId = $this->googleCalendarService->createGoogleEvent($cleaningRequest);

            $cleaningRequest->setGoogleEventId($googleEventId);
            $cleaningRequest->setSyncSource(SyncLog::SOURCE_APP);
            $cleaningRequest->setSyncStatus('synced');
            $cleaningRequest->setLastSyncAt(new \DateTime());

            $this->logSyncAction(
                $cleaningRequest,
                SyncLog::ACTION_CREATE,
                SyncLog::SOURCE_APP,
                sprintf('Event Google créé depuis l\'admin pour la mission #%d', $cleaningRequest->getId())
            );
        } catch (\Throwable $e) {
            $cleaningRequest->setSyncStatus('error');
            $this->logSyncAction(
                $cleaningRequest,
                SyncLog::ACTION_ERROR,
                SyncLog::SOURCE_APP,
                sprintf('Échec création event Google pour mission #%d : %s', $cleaningRequest->getId(), $e->getMessage())
            );
            $this->logger->error('GoogleSyncService::pushCreate a échoué', ['exception' => $e->getMessage()]);
        }

        $this->entityManager->flush();
    }

    public function pushUpdate(CleaningRequest $cleaningRequest): void
    {
        if (empty($cleaningRequest->getGoogleEventId())) {
            $this->pushCreate($cleaningRequest);
            return;
        }

        try {
            $this->googleCalendarService->updateGoogleEvent($cleaningRequest);

            $cleaningRequest->setSyncStatus('synced');
            $cleaningRequest->setLastSyncAt(new \DateTime());

            $this->logSyncAction(
                $cleaningRequest,
                SyncLog::ACTION_UPDATE,
                SyncLog::SOURCE_APP,
                sprintf('Event Google mis à jour depuis l\'admin pour la mission #%d', $cleaningRequest->getId())
            );
        } catch (\Throwable $e) {
            $cleaningRequest->setSyncStatus('error');
            $this->logSyncAction(
                $cleaningRequest,
                SyncLog::ACTION_ERROR,
                SyncLog::SOURCE_APP,
                sprintf('Échec mise à jour event Google pour mission #%d : %s', $cleaningRequest->getId(), $e->getMessage())
            );
            $this->logger->error('GoogleSyncService::pushUpdate a échoué', ['exception' => $e->getMessage()]);
            throw $e;
        } finally {
            $this->entityManager->flush();
        }
    }

    public function revertGoogleEvent(CleaningRequest $cleaningRequest): void
    {
        $this->pushUpdate($cleaningRequest);
    }

    public function pushDelete(CleaningRequest $cleaningRequest): void
    {
        $googleEventId = $cleaningRequest->getGoogleEventId();

        if (empty($googleEventId)) {
            return;
        }

        try {
            $this->googleCalendarService->deleteGoogleEvent($googleEventId);

            $this->logSyncAction(
                $cleaningRequest,
                SyncLog::ACTION_DELETE,
                SyncLog::SOURCE_APP,
                sprintf('Event Google supprimé depuis l\'admin pour la mission #%d', $cleaningRequest->getId())
            );
        } catch (\Throwable $e) {
            $this->logSyncAction(
                $cleaningRequest,
                SyncLog::ACTION_ERROR,
                SyncLog::SOURCE_APP,
                sprintf('Échec suppression event Google pour mission #%d : %s', $cleaningRequest->getId(), $e->getMessage())
            );
            $this->logger->error('GoogleSyncService::pushDelete a échoué', ['exception' => $e->getMessage()]);
        }

        $this->entityManager->flush();
    }

    private function detectAndDeleteRemovedEvents(\DateTimeInterface $timeMin, \DateTimeInterface $timeMax, array $seenGoogleEventIds): int
    {
        $knownRequests = $this->cleaningRequestRepository->createQueryBuilder('cr')
            ->where('cr.googleEventId IS NOT NULL')
            ->andWhere('cr.scheduledDate >= :timeMin')
            ->andWhere('cr.scheduledDate <= :timeMax')
            ->setParameter('timeMin', $timeMin)
            ->setParameter('timeMax', $timeMax)
            ->getQuery()
            ->getResult();

        $deletedCount = 0;

        foreach ($knownRequests as $cleaningRequest) {
            if (in_array($cleaningRequest->getGoogleEventId(), $seenGoogleEventIds, true)) {
                continue;
            }

            $this->logSyncAction(
                null,
                SyncLog::ACTION_DELETE,
                SyncLog::SOURCE_GOOGLE,
                sprintf(
                    'Mission #%d supprimée : event Google %s introuvable (supprimé dans Google Agenda)',
                    $cleaningRequest->getId(),
                    $cleaningRequest->getGoogleEventId()
                )
            );

            $this->entityManager->remove($cleaningRequest);
            ++$deletedCount;
        }

        return $deletedCount;
    }

    private function matchProperty(GoogleEvent $event): ?Property
    {
        $summary = $event->getSummary() ?? '';
        $properties = $this->propertyRepository->findAll();

        if (count($properties) === 0) {
            return null;
        }

        $sectorFromTitle = $this->sectorAssignmentService->guessSectorFromText($summary);

        if ($sectorFromTitle !== null) {
            foreach ($properties as $property) {
                if ($this->sectorAssignmentService->resolveSector($property) === $sectorFromTitle) {
                    return $property;
                }
            }
        }

        if (count($properties) === 1) {
            return $properties[0];
        }

        return null;
    }

    private function logSyncAction(?CleaningRequest $cleaningRequest, string $action, string $source, string $message): void
    {
        $log = new SyncLog();
        $log->setCleaningRequest($cleaningRequest);
        $log->setAction($action);
        $log->setSource($source);
        $log->setMessage($message);

        $this->entityManager->persist($log);
    }
}