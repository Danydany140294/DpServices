<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service central de création des notifications in-app (J37-J39, J41).
 * Toute notification visible dans la cloche de l'interface passe par ici.
 */
class NotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
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
}