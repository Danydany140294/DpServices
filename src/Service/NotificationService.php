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

    public function notifyMissionReminder(User $cleaner, CleaningRequest $cleaningRequest): void
    {
        $message = sprintf(
            'Rappel : mission non consultée — %s le %s à %s',
            $cleaningRequest->getProperty()->getName(),
            $cleaningRequest->getScheduledDate()->format('d/m/Y'),
            $cleaningRequest->getScheduledTime()->format('H:i')
        );

        $this->notify($cleaner, Notification::TYPE_MISSION_REMINDER, $message);

        try {
            $this->cleanerSmsService->sendSms($cleaner, $message);
        } catch (\Throwable $e) {
            $this->logger->warning('NotificationService: échec envoi SMS relance mission (notification in-app envoyée malgré tout)', [
                'cleaner_id' => $cleaner->getId(),
                'cleaning_request_id' => $cleaningRequest->getId(),
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * J53 : notifie le propriétaire (in-app + SMS) que sa mission de ménage
     * a été effectuée. Non-bloquant : si le propriétaire n'a pas de
     * téléphone ou si l'envoi SMS échoue, la notification in-app est
     * quand même créée et l'erreur est simplement loguée.
     */
    public function notifyMissionCompleted(User $owner, CleaningRequest $cleaningRequest): void
    {
        $message = sprintf(
            'Ménage terminé : %s ',
            $cleaningRequest->getProperty(),
            $cleaningRequest->getScheduledDate()->format('d/m/Y'),
            $cleaningRequest->getScheduledTime()->format('H:i')
        );

        $this->notify($owner, Notification::TYPE_MISSION_COMPLETED, $message);

        try {
            $this->cleanerSmsService->sendSms($owner, $message);
        } catch (\Throwable $e) {
            $this->logger->warning('NotificationService: échec envoi SMS mission terminée (notification in-app envoyée malgré tout)', [
                'owner_id' => $owner->getId(),
                'cleaning_request_id' => $cleaningRequest->getId(),
                'exception' => $e->getMessage(),
            ]);
        }
    }
}