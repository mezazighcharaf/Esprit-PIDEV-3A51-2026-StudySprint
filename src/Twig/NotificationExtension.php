<?php

namespace App\Twig;

use App\Entity\User;
use App\Repository\NotificationRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

class NotificationExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly NotificationRepository $notificationRepo,
        private readonly Security $security
    ) {
    }

    public function getGlobals(): array
    {
        $user = $this->security->getUser();
        $unreadCount = 0;

        if ($user instanceof User) {
            $unreadCount = $this->notificationRepo->countUnread($user);
        }

        return [
            'unread_notifications_count' => $unreadCount,
        ];
    }
}
