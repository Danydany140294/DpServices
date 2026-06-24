<?php

namespace App\Service;

use Google\Client as GoogleClient;
use Google\Service\Calendar as GoogleCalendar;
use Psr\Log\LoggerInterface;

/**
 * Service de connexion et de synchronisation avec Google Calendar.
 *
 * Utilise un compte Google unique (admin/entreprise) via OAuth2.
 * Le refresh token est généré une seule fois (commande app:google-auth, créée en J2)
 * puis stocké dans .env.local pour permettre un accès automatique sans
 * ré-authentification manuelle.
 */
class GoogleCalendarService
{
    private GoogleClient $client;
    private ?GoogleCalendar $calendarService = null;

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
     * Test de connexion simple : liste les prochains événements du calendrier configuré.
     * Utilisé en J2 pour valider que la connexion OAuth fonctionne.
     *
     * @return array<int, array{id: string, summary: string, start: string|null}>
     */
    public function testConnection(int $maxResults = 5): array
    {
        $calendar = $this->getCalendarService();

        $events = $calendar->events->listEvents($this->googleCalendarId, [
            'maxResults' => $maxResults,
            'orderBy' => 'startTime',
            'singleEvents' => true,
            'timeMin' => (new \DateTime())->format(\DateTime::RFC3339),
        ]);

        $result = [];
        foreach ($events->getItems() as $event) {
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
}