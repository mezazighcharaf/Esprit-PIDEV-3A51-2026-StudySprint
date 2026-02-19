<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class NotificationService
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
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

        return $notification;
    }
}
