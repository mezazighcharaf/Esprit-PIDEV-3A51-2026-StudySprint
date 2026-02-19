<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\UserProfile;
use Doctrine\ORM\EntityManagerInterface;

class StreakService
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function recordActivity(User $user): void
    {
        $profile = $user->getProfile();
        if (!$profile) {
            $profile = new UserProfile();
            $profile->setUser($user);
            $user->setProfile($profile);
            $this->em->persist($profile);
        }

        $today = new \DateTimeImmutable('today');
        $lastActivity = $profile->getLastActivityDate();

        if ($lastActivity && $lastActivity->format('Y-m-d') === $today->format('Y-m-d')) {
            return; // Already recorded today
        }

        if ($lastActivity && $lastActivity->format('Y-m-d') === $today->modify('-1 day')->format('Y-m-d')) {
            // Consecutive day
            $profile->setCurrentStreak($profile->getCurrentStreak() + 1);
        } else {
            // Streak broken or first activity
            $profile->setCurrentStreak(1);
        }

        if ($profile->getCurrentStreak() > $profile->getLongestStreak()) {
            $profile->setLongestStreak($profile->getCurrentStreak());
        }

        $profile->setLastActivityDate($today);
        $this->em->flush();
    }
}
