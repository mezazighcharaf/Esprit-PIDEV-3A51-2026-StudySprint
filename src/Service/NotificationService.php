<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

class NotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ?HubInterface $mercureHub = null,
    ) {
    }

    public function create(User $user, string $title, string $message, string $type = Notification::TYPE_INFO, ?string $link = null): Notification
    {
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setTitle($title);
        $notification->setMessage($message);
        $notification->setType($type);
        $notification->setLink($link);

        $this->em->persist($notification);
        $this->em->flush();

        $this->publishToMercure($user, $notification);

        return $notification;
    }

    private function publishToMercure(User $user, Notification $notification): void
    {
        if ($this->mercureHub === null) {
            return;
        }

        try {
            $topic = sprintf('studysprint/notifications/user/%d', $user->getId());

            $update = new Update(
                $topic,
                json_encode([
                    'id'      => $notification->getId(),
                    'title'   => $notification->getTitle(),
                    'message' => $notification->getMessage(),
                    'type'    => $notification->getType(),
                    'link'    => $notification->getLink(),
                ]),
                true // private
            );

            $this->mercureHub->publish($update);
        } catch (\Throwable) {
            // Mercure hub may not be running in development — fail silently
        }
    }
}
