<?php

namespace App\Repository;

use App\Entity\ChatbotConfig;
use App\Entity\StudyGroup;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ChatbotConfig>
 */
class ChatbotConfigRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChatbotConfig::class);
    }

    public function findByGroup(StudyGroup $group): ?ChatbotConfig
    {
        return $this->findOneBy(['group' => $group]);
    }

    public function findEnabledByGroup(StudyGroup $group): ?ChatbotConfig
    {
        return $this->findOneBy(['group' => $group, 'isEnabled' => true]);
    }
}
