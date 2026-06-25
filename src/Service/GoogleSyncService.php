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

/**
 * Service de synchronisation Google Calendar -> Symfony (sens "pull").
 *
 * Transforme les événements Google Agenda en missions CleaningRequest,
 * avec détection des doublons (via googleEventId) et affectation automatique
 * du salarié selon le secteur géographique du bien.
 *
 * Règle V3 : Google Agenda n'est jamais la source de vérité. Cette méthode
 * ne fait que CRÉER de nouvelles missions à partir de nouveaux événements.
 * La gestion des modifications/conflits (event Google déjà connu qui change)
 * sera traitée en J22-J28 (semaine 4 de la roadmap V3).
 *
 * Stratégie de correspondance bien <-> événement :
 * Les titres d'événements Google de Dany sont basés sur la VILLE/ZONE
 * (ex: "Ménage Nîmes", "Ménage immeuble Nîmes (appartement 1 et 3)"),
 * pas sur le nom exact du bien. On fait donc correspondre sur le secteur
 * déduit du titre, pas sur Property.name.
 *
 * Type de prestation par défaut : Google ne précise jamais le type de
 * prestation (CleaningService). On utilise donc un service par défaut
 * ("Ménage simple", id=3 en base) ; à corriger manuellement après coup
 * dans l'app si une mission nécessite en réalité un "Ménage approfondi".
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
    ) {
    }

    /**
     * Lance la synchronisation "pull" : lit les événements Google sur la période
     * donnée, crée les missions correspondantes pour les événements inconnus.
     *
     * @return array{created: int, skipped: int, errors: int}
     */
    public function pullFromGoogle(?\DateTimeInterface $timeMin = null, ?\DateTimeInterface $timeMax = null): array
    {
        $stats = ['created' => 0, 'skipped' => 0, 'errors' => 0];

        $defaultService = $this->cleaningServiceRepository->find(self::DEFAULT_SERVICE_ID);
        if ($defaultService === null) {
            throw new \RuntimeException(sprintf(
                'CleaningService par défaut (id=%d) introuvable en base. Vérifiez la table cleaning_service.',
                self::DEFAULT_SERVICE_ID
            ));
        }

        $events = $this->googleCalendarService->listEvents($timeMin, $timeMax);

        foreach ($events as $event) {
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

        $this->entityManager->flush();

        return $stats;
    }

    /**
     * Traite un événement Google unique : crée une mission si l'event est inconnu.
     * Retourne la CleaningRequest créée, ou null si l'event était déjà connu (skip)
     * ou non convertible.
     */
    private function processEvent(GoogleEvent $event, CleaningService $defaultService): ?CleaningRequest
    {
        $googleEventId = $event->getId();

        $existing = $this->cleaningRequestRepository->findOneBy(['googleEventId' => $googleEventId]);
        if ($existing !== null) {
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

        return $cleaningRequest;
    }

    /**
     * Transforme un événement Google en une nouvelle CleaningRequest.
     * Retourne null si aucun bien correspondant n'est trouvé.
     */
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
        }

        return $cleaningRequest;
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