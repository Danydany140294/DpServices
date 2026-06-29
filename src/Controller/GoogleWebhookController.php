<?php

namespace App\Controller;

use App\Service\GoogleSyncService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * TODO PROD — Webhook Google Calendar (notifications push, J33).
 *
 * Cette route reçoit les notifications "push" de Google quand un événement
 * change sur le calendrier surveillé. Elle complète (sans remplacer) le cron
 * de polling (app:sync-google-pull, J29) : le webhook permet une réaction
 * quasi instantanée, le cron reste un filet de sécurité en cas de notification
 * manquée ou de canal expiré.
 *
 * PRÉREQUIS AVANT ACTIVATION EN PRODUCTION :
 *   1. Domaine HTTPS public (Google refuse les URLs http:// ou localhost).
 *   2. Enregistrer un "canal de notification" auprès de Google via l'API
 *      events.watch() (méthode à ajouter dans GoogleCalendarService quand
 *      le domaine sera connu — non faisable en local).
 *   3. Le canal expire au bout de 7 jours maximum : prévoir un renouvellement
 *      automatique (ex: une commande cron quotidienne qui ré-enregistre le canal).
 *   4. Vérifier l'en-tête "X-Goog-Channel-Token" sur chaque requête entrante
 *      pour s'assurer que la notification vient bien de Google et pas d'un tiers
 *      qui aurait deviné l'URL (à générer et stocker au moment du watch()).
 *
 * Tant que ces prérequis ne sont pas remplis, cette route reste fonctionnelle
 * mais n'est jamais appelée par personne (aucun canal n'aura été enregistré
 * auprès de Google), donc elle est inactive de fait, sans risque.
 */
class GoogleWebhookController extends AbstractController
{
    public function __construct(
        private readonly GoogleSyncService $googleSyncService,
        private readonly LoggerInterface $logger,
        private readonly string $googleWebhookChannelToken,
    ) {
    }

    #[Route('/webhook/google-calendar', name: 'app_google_webhook', methods: ['POST'])]
    public function handle(Request $request): Response
    {
        // TODO PROD : Google envoie ces en-têtes sur chaque notification.
        // X-Goog-Channel-Id, X-Goog-Resource-Id, X-Goog-Resource-State,
        // X-Goog-Channel-Token (notre jeton de vérification, voir watch()).
        $channelToken = $request->headers->get('X-Goog-Channel-Token');
        $resourceState = $request->headers->get('X-Goog-Resource-State');

        // Vérification du jeton : si absent ou incorrect, on ignore la requête.
        // Tant que GOOGLE_WEBHOOK_CHANNEL_TOKEN n'est pas configuré (vide en
        // local/dev), cette vérification échoue systématiquement par sécurité —
        // c'est volontaire, le webhook ne doit jamais traiter de requête tant
        // qu'aucun canal n'a été correctement enregistré auprès de Google.
        if (empty($this->googleWebhookChannelToken) || $channelToken !== $this->googleWebhookChannelToken) {
            $this->logger->warning('GoogleWebhookController: requête reçue avec un jeton invalide ou absent (ignorée)', [
                'resource_state' => $resourceState,
            ]);

            return new Response('Forbidden', Response::HTTP_FORBIDDEN);
        }

        // "sync" est l'état envoyé par Google lors de la création initiale du
        // canal (notification de confirmation, pas un vrai changement) : on
        // l'accuse réception sans déclencher de synchronisation.
        if ($resourceState === 'sync') {
            $this->logger->info('GoogleWebhookController: notification de confirmation de canal reçue');

            return new Response('OK', Response::HTTP_OK);
        }

        $this->logger->info('GoogleWebhookController: changement détecté, synchronisation déclenchée', [
            'resource_state' => $resourceState,
        ]);

        try {
            $this->googleSyncService->pullFromGoogle();
        } catch (\Throwable $e) {
            $this->logger->error('GoogleWebhookController: échec de la synchronisation déclenchée par webhook', [
                'exception' => $e->getMessage(),
            ]);

            // On répond 200 malgré l'échec : répondre une erreur HTTP inciterait
            // Google à réessayer en boucle. Le cron (J29) rattrapera l'événement
            // manqué au prochain passage de toute façon.
        }

        return new Response('OK', Response::HTTP_OK);
    }
}