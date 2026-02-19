<?php

namespace App\EventSubscriber;

use App\Entity\ActivityLog;
use App\Entity\Quiz;
use App\Entity\FlashcardDeck;
use App\Entity\StudyGroup;
use App\Entity\QuizAttempt;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;

#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postRemove)]
class EntityActivitySubscriber
{
    private const TRACKED_ENTITIES = [
        Quiz::class,
        FlashcardDeck::class,
        StudyGroup::class,
        QuizAttempt::class,
        User::class,
    ];

    public function __construct(private readonly Security $security)
    {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->log($args->getObject(), 'CREATE', $args);
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->log($args->getObject(), 'UPDATE', $args);
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $this->log($args->getObject(), 'DELETE', $args);
    }

    private function log(object $entity, string $action, $args): void
    {
        // Don't log ActivityLog itself to avoid infinite loop
        if ($entity instanceof ActivityLog) {
            return;
        }

        $className = get_class($entity);
        if (!in_array($className, self::TRACKED_ENTITIES)) {
            return;
        }

        $shortName = (new \ReflectionClass($entity))->getShortName();
        $entityId = method_exists($entity, 'getId') ? $entity->getId() : 0;

        $label = null;
        if (method_exists($entity, 'getTitle')) {
            $label = $entity->getTitle();
        } elseif (method_exists($entity, 'getName')) {
            $label = $entity->getName();
        } elseif (method_exists($entity, 'getEmail')) {
            $label = $entity->getEmail();
        }

        $log = new ActivityLog();
        $log->setAction($action);
        $log->setEntityType($shortName);
        $log->setEntityId($entityId ?? 0);
        $log->setEntityLabel($label ? substr($label, 0, 255) : null);

        $user = $this->security->getUser();
        if ($user instanceof User) {
            $log->setUser($user);
        }

        $em = $args->getObjectManager();
        $em->persist($log);
        $em->flush();
    }
}
