<?php

namespace App\Service;

use App\Entity\CleaningRequest;
use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service central de création des notifications in-app (J37-J39, J41).
 * Toute notification visible dans la cloche de l'interface passe par ici.
 */
class NotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CleanerSmsService $cleanerSmsService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function notify(User $recipient, string $type, string $message, ?string $link = null): Notification
    {
        $notification = new Notification();
        $notification->setRecipient($recipient);
        $notification->setType($type);
        $notification->setMessage($message);
        $notification->setLink($link);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        return $notification;
    }

    /**
     * J37 : notifie un salarié qu'une mission lui a été assignée, à la fois
     * en notification in-app ET par SMS, peu importe l'origine de la mission
     * (création manuelle admin OU synchronisation automatique Google).
     *
     * Le SMS est volontairement non-bloquant : si l'envoi échoue (pas de
     * téléphone, API Brevo indisponible...), la notification in-app reste
     * créée normalement et l'erreur est seulement loguée.
     */
    public function notifyMissionAssigned(User $cleaner, CleaningRequest $cleaningRequest, string $calendarUrl): void
    {
        $message = sprintf(
            'Nouvelle mission : %s le %s à %s',
            $cleaningRequest->getProperty()->getName(),
            $cleaningRequest->getScheduledDate()->format('d/m/Y'),
            $cleaningRequest->getScheduledTime()->format('H:i')
        );

        $this->notify($cleaner, Notification::TYPE_MISSION_ASSIGNED, $message, $calendarUrl);

        try {
            $this->cleanerSmsService->sendSms($cleaner, $message);
        } catch (\Throwable $e) {
            $this->logger->warning('NotificationService: échec envoi SMS mission assignée (notification in-app envoyée malgré tout)', [
                'cleaner_id' => $cleaner->getId(),
                'cleaning_request_id' => $cleaningRequest->getId(),
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * J39/J40 : notifie le salarié assigné qu'une mission est annulée,
     * en notification in-app ET par SMS (même logique que J37).
     */
    public function notifyMissionCancelled(User $cleaner, CleaningRequest $cleaningRequest): void
    {
        $message = sprintf(
            'Mission annulée : %s le %s à %s',
            $cleaningRequest->getProperty()->getName(),
            $cleaningRequest->getScheduledDate()->format('d/m/Y'),
            $cleaningRequest->getScheduledTime()->format('H:i')
        );

        $this->notify($cleaner, Notification::TYPE_MISSION_CANCELLED, $message);

        try {
            $this->cleanerSmsService->sendSms($cleaner, $message);
        } catch (\Throwable $e) {
            $this->logger->warning('NotificationService: échec envoi SMS mission annulée (notification in-app envoyée malgré tout)', [
                'cleaner_id' => $cleaner->getId(),
                'cleaning_request_id' => $cleaningRequest->getId(),
                'exception' => $e->getMessage(),
            ]);
        }
    }
}