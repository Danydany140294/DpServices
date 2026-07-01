<?php

namespace App\Service;

use App\Entity\CleaningRequest;
use Google\Client as GoogleClient;
use Google\Service\Calendar as GoogleCalendar;
use Google\Service\Calendar\Event as GoogleEvent;
use Google\Service\Calendar\EventDateTime;
use Psr\Log\LoggerInterface;

/**
 * Service de connexion et de synchronisation avec Google Calendar.
 *
 * Utilise un compte Google unique (admin/entreprise) via OAuth2.
 * Le refresh token est généré une seule fois (commande app:google-auth)
 * puis stocké dans .env.local pour permettre un accès automatique sans
 * ré-authentification manuelle.
 */
class GoogleCalendarService
{
    private GoogleClient $client;
    private ?GoogleCalendar $calendarService = null;

    private const HEX_TO_GOOGLE_COLOR_ID = [
        '#7986cb' => '1',  // Lavande
        '#33b679' => '2',  // Sauge
        '#8e24aa' => '3',  // Raisin
        '#e67c73' => '4',  // Flamant
        '#f6bf26' => '5',  // Banane
        '#f4511e' => '6',  // Mandarine
        '#039be5' => '7',  // Paon
        '#616161' => '8',  // Graphite
        '#3f51b5' => '9',  // Myrtille
        '#0b8043' => '10', // Basilic
        '#d50000' => '11', // Tomate
    ];

    public function __construct(
        private readonly string $googleClientId,
        private readonly string $googleClientSecret,
        private readonly string $googleRedirectUri,
        private readonly string $googleCalendarId,
        private readonly ?string $googleRefreshToken,
        private readonly LoggerInterface $logger,
    ) {
        $this->client = new GoogleClient();
        $this->client->setClientId($this->googleClientId);
        $this->client->setClientSecret($this->googleClientSecret);
        $this->client->setRedirectUri($this->googleRedirectUri);
        $this->client->addScope(GoogleCalendar::CALENDAR);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent');
    }

    /**
     * Génère l'URL d'autorisation Google (utilisée une seule fois pour obtenir
     * le refresh token initial, via la commande app:google-auth).
     */
    public function createAuthUrl(): string
    {
        return $this->client->createAuthUrl();
    }

    /**
     * Échange le code d'autorisation reçu par Google contre un jeu de tokens
     * (access token + refresh token). Utilisé uniquement lors de la configuration
     * initiale (commande app:google-auth).
     *
     * @return array{access_token: string, refresh_token?: string, expires_in: int}
     */
    public function fetchTokenWithAuthCode(string $authCode): array
    {
        $token = $this->client->fetchAccessTokenWithAuthCode($authCode);

        if (isset($token['error'])) {
            throw new \RuntimeException(sprintf(
                'Erreur lors de l\'échange du code Google : %s',
                $token['error_description'] ?? $token['error']
            ));
        }

        return $token;
    }

    /**
     * Initialise le client Google avec le refresh token stocké en config,
     * et renouvelle automatiquement l'access token si besoin.
     */
    private function authenticate(): void
    {
        if (empty($this->googleRefreshToken)) {
            throw new \RuntimeException(
                'GOOGLE_CALENDAR_REFRESH_TOKEN absent. Lancez la commande app:google-auth pour générer le refresh token initial.'
            );
        }

        $this->client->refreshToken($this->googleRefreshToken);
    }

    /**
     * Retourne le client Google authentifié (lazy init).
     */
    public function getClient(): GoogleClient
    {
        if (!$this->client->getAccessToken()) {
            $this->authenticate();
        }

        return $this->client;
    }

    /**
     * Retourne le service Calendar prêt à l'emploi (lazy init).
     */
    public function getCalendarService(): GoogleCalendar
    {
        if ($this->calendarService === null) {
            $this->calendarService = new GoogleCalendar($this->getClient());
        }

        return $this->calendarService;
    }

    /**
     * Retourne l'identifiant du calendrier configuré (généralement "primary").
     */
    public function getCalendarId(): string
    {
        return $this->googleCalendarId;
    }

    /**
     * Liste les événements Google bruts (objets GoogleEvent) sur une période donnée.
     *
     * @return GoogleEvent[]
     */
    public function listEvents(?\DateTimeInterface $timeMin = null, ?\DateTimeInterface $timeMax = null, int $maxResults = 250): array
    {
        $calendar = $this->getCalendarService();

        $params = [
            'maxResults' => $maxResults,
            'orderBy' => 'startTime',
            'singleEvents' => true,
            'timeMin' => ($timeMin ?? new \DateTime('-1 day'))->format(\DateTime::RFC3339),
        ];

        if ($timeMax !== null) {
            $params['timeMax'] = $timeMax->format(\DateTime::RFC3339);
        }

        $events = $calendar->events->listEvents($this->googleCalendarId, $params);

        return $events->getItems();
    }

    /**
     * Test de connexion simple : liste les prochains événements du calendrier configuré.
     * Format simplifié pour affichage console (commande app:google-test).
     *
     * @return array<int, array{id: string, summary: string, start: string|null}>
     */
    public function testConnection(int $maxResults = 5): array
    {
        $events = $this->listEvents(new \DateTime(), null, $maxResults);

        $result = [];
        foreach ($events as $event) {
            $start = $event->getStart();
            $result[] = [
                'id' => $event->getId(),
                'summary' => $event->getSummary() ?? '(sans titre)',
                'start' => $start->getDateTime() ?? $start->getDate(),
            ];
        }

        $this->logger->info('GoogleCalendarService: test de connexion réussi', [
            'nb_events' => count($result),
        ]);

        return $result;
    }

    /**
     * Construit le titre et la description d'un événement Google à partir
     * d'une mission. Centralisé ici pour que create/update restent cohérents.
     */
    private function buildEventTitle(CleaningRequest $cleaningRequest): string
    {
        $property = $cleaningRequest->getProperty();
        $propertyName = $property?->getName() ?? 'Bien inconnu';

        return sprintf('Ménage %s', $propertyName);
    }

    private function resolveGoogleColorId(?string $hex): ?string
    {
        if ($hex === null) {
            return null;
        }

        return self::HEX_TO_GOOGLE_COLOR_ID[strtolower($hex)] ?? null;
    }

    /**
     * Construit un objet GoogleEvent (titre, description, horaires) à partir
     * d'une mission Symfony. Durée déduite du CleaningService associé.
     */
    private function buildGoogleEvent(CleaningRequest $cleaningRequest): GoogleEvent
    {
        $scheduledDate = $cleaningRequest->getScheduledDate();
        $scheduledTime = $cleaningRequest->getScheduledTime();

        $parisTimezone = new \DateTimeZone('Europe/Paris');

        $start = new \DateTime(
            $scheduledDate->format('Y-m-d') . ' ' . $scheduledTime->format('H:i:s'),
            $parisTimezone
        );

        $durationMinutes = $cleaningRequest->getService()?->getDuration() ?? 60;
        $end = (clone $start)->modify(sprintf('+%d minutes', $durationMinutes));

        $event = new GoogleEvent();
        $event->setSummary($this->buildEventTitle($cleaningRequest));
        $event->setDescription($cleaningRequest->getComment() ?? '');

        $colorId = $this->resolveGoogleColorId($cleaningRequest->getProperty()?->getColor());
        if ($colorId !== null) {
            $event->setColorId($colorId);
        }

        $eventStart = new EventDateTime();
        $eventStart->setDateTime($start->format(\DateTime::RFC3339));
        $eventStart->setTimeZone('Europe/Paris');
        $event->setStart($eventStart);

        $eventEnd = new EventDateTime();
        $eventEnd->setDateTime($end->format(\DateTime::RFC3339));
        $eventEnd->setTimeZone('Europe/Paris');
        $event->setEnd($eventEnd);

        return $event;
    }

    /**
     * Crée un nouvel événement Google Agenda à partir d'une mission Symfony.
     * Retourne l'ID de l'événement Google créé (à stocker dans CleaningRequest.googleEventId).
     */
    public function createGoogleEvent(CleaningRequest $cleaningRequest): string
    {
        $calendar = $this->getCalendarService();
        $event = $this->buildGoogleEvent($cleaningRequest);

        $createdEvent = $calendar->events->insert($this->googleCalendarId, $event);

        $this->logger->info('GoogleCalendarService: événement créé', [
            'cleaning_request_id' => $cleaningRequest->getId(),
            'google_event_id' => $createdEvent->getId(),
        ]);

        return $createdEvent->getId();
    }

    /**
     * Met à jour un événement Google existant à partir des données actuelles
     * de la mission Symfony. La mission doit déjà avoir un googleEventId.
     */
    public function updateGoogleEvent(CleaningRequest $cleaningRequest): void
    {
        $googleEventId = $cleaningRequest->getGoogleEventId();

        if (empty($googleEventId)) {
            throw new \InvalidArgumentException(
                'Impossible de mettre à jour : cette mission n\'a pas de googleEventId. Utilisez createGoogleEvent() à la place.'
            );
        }

        $calendar = $this->getCalendarService();
        $event = $this->buildGoogleEvent($cleaningRequest);

        $calendar->events->update($this->googleCalendarId, $googleEventId, $event);

        $this->logger->info('GoogleCalendarService: événement mis à jour', [
            'cleaning_request_id' => $cleaningRequest->getId(),
            'google_event_id' => $googleEventId,
        ]);
    }

    /**
     * Supprime un événement Google Agenda à partir de son ID.
     * Ne lève pas d'exception si l'événement est déjà absent côté Google
     * (cas où il aurait été supprimé manuellement par l'admin).
     */
    public function deleteGoogleEvent(string $googleEventId): void
    {
        $calendar = $this->getCalendarService();

        try {
            $calendar->events->delete($this->googleCalendarId, $googleEventId);
            $this->logger->info('GoogleCalendarService: événement supprimé', [
                'google_event_id' => $googleEventId,
            ]);
        } catch (\Google\Service\Exception $e) {
            if ($e->getCode() === 410 || $e->getCode() === 404) {
                // Déjà supprimé côté Google : on considère que l'objectif est atteint.
                $this->logger->info('GoogleCalendarService: événement déjà absent côté Google (ignoré)', [
                    'google_event_id' => $googleEventId,
                ]);

                return;
            }

            throw $e;
        }
    }

    /**
     * TODO PROD — Enregistre un canal de notification "watch" auprès de Google
     * Calendar, pour recevoir les changements en temps réel via webhook (J33).
     *
     * NE PEUT PAS FONCTIONNER EN LOCAL : Google exige une URL de callback en
     * HTTPS public, jamais http:// ni localhost. Cette méthode n'est appelée
     * par aucun code actif tant que le domaine de production n'est pas connu.
     *
     * Usage prévu une fois déployé (à intégrer dans une commande dédiée,
     * ex: app:google-webhook-register, exécutée manuellement après déploiement
     * puis renouvelée automatiquement avant l'expiration à 7 jours) :
     *
     *   $channelId = bin2hex(random_bytes(16));
     *   $channelToken = bin2hex(random_bytes(32)); // à stocker dans .env.local
     *   $googleCalendarService->watch(
     *       $channelId,
     *       $channelToken,
     *       'https://votre-domaine.fr/webhook/google-calendar'
     *   );
     *
     * @return array{id: string, expiration: string} Infos du canal créé par Google
     */
    public function watch(string $channelId, string $channelToken, string $callbackUrl): array
    {
        $calendar = $this->getCalendarService();

        $channel = new \Google\Service\Calendar\Channel();
        $channel->setId($channelId);
        $channel->setType('web_hook');
        $channel->setAddress($callbackUrl);
        $channel->setToken($channelToken);

        $result = $calendar->events->watch($this->googleCalendarId, $channel);

        $this->logger->info('GoogleCalendarService: canal de notification enregistré', [
            'channel_id' => $result->getId(),
            'expiration' => $result->getExpiration(),
        ]);

        return [
            'id' => $result->getId(),
            'expiration' => $result->getExpiration(),
        ];
    }
}