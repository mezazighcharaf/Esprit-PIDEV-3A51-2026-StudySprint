<?php

namespace App\EventListener;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 0)]
class UserActivityListener
{
    public function __construct(
        private Security $security,
        private EntityManagerInterface $entityManager
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        // Only track main requests, not sub-requests
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->security->getUser();

        // Only track authenticated users
        if (!$user instanceof User) {
            return;
        }

        // Update last activity timestamp
        $user->setLastActivityAt(new \DateTimeImmutable());
        
        // Persist the change
        $this->entityManager->flush();
    }
}
