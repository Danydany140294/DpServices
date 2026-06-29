<?php

namespace App\Twig;

use App\Entity\User;
use App\Repository\NotificationRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class NotificationExtension extends AbstractExtension
{
    public function __construct(
        private readonly NotificationRepository $notificationRepository,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('unread_notifications_count', $this->getUnreadCount(...)),
            new TwigFunction('recent_notifications', $this->getRecent(...)),
        ];
    }

    public function getUnreadCount(?User $user): int
    {
        if ($user === null) {
            return 0;
        }

        return $this->notificationRepository->countUnreadFor($user);
    }

    public function getRecent(?User $user, int $limit = 10): array
    {
        if ($user === null) {
            return [];
        }

        return $this->notificationRepository->findRecentFor($user, $limit);
    }
}
