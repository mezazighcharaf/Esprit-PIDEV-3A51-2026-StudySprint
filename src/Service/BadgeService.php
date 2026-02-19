<?php

namespace App\Service;

use App\Entity\Badge;
use App\Entity\User;
use App\Entity\UserBadge;
use App\Repository\BadgeRepository;
use App\Repository\QuizAttemptRepository;
use App\Repository\UserBadgeRepository;
use Doctrine\ORM\EntityManagerInterface;

class BadgeService
{
    private const BADGE_DEFINITIONS = [
        ['code' => 'first_quiz', 'name' => 'Premier Quiz', 'description' => 'Terminer votre premier quiz', 'icon' => '🎯', 'color' => '#667eea'],
        ['code' => 'quiz_5', 'name' => 'Assidu', 'description' => 'Terminer 5 quiz', 'icon' => '📚', 'color' => '#f59e0b'],
        ['code' => 'quiz_10', 'name' => 'Expert', 'description' => 'Terminer 10 quiz', 'icon' => '🏆', 'color' => '#10b981'],
        ['code' => 'quiz_25', 'name' => 'Maître', 'description' => 'Terminer 25 quiz', 'icon' => '👑', 'color' => '#ef4444'],
        ['code' => 'perfect_score', 'name' => 'Score Parfait', 'description' => 'Obtenir 100% à un quiz', 'icon' => '⭐', 'color' => '#f59e0b'],
        ['code' => 'streak_3', 'name' => 'En Forme', 'description' => '3 jours consécutifs d\'activité', 'icon' => '🔥', 'color' => '#ef4444'],
        ['code' => 'streak_7', 'name' => 'Inarrêtable', 'description' => '7 jours consécutifs d\'activité', 'icon' => '💪', 'color' => '#764ba2'],
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly BadgeRepository $badgeRepo,
        private readonly UserBadgeRepository $userBadgeRepo,
        private readonly QuizAttemptRepository $attemptRepo
    ) {
    }

    public function initBadges(): int
    {
        $count = 0;
        foreach (self::BADGE_DEFINITIONS as $def) {
            $existing = $this->badgeRepo->findOneBy(['code' => $def['code']]);
            if (!$existing) {
                $badge = new Badge();
                $badge->setCode($def['code']);
                $badge->setName($def['name']);
                $badge->setDescription($def['description']);
                $badge->setIcon($def['icon']);
                $badge->setColor($def['color']);
                $this->em->persist($badge);
                $count++;
            }
        }
        $this->em->flush();
        return $count;
    }

    public function checkAndAwardBadges(User $user): array
    {
        $awarded = [];

        $completedAttempts = $this->attemptRepo->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.user = :user')
            ->andWhere('a.isCompleted = true')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        $perfectAttempts = $this->attemptRepo->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.user = :user')
            ->andWhere('a.isCompleted = true')
            ->andWhere('a.score = 100')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        if ($completedAttempts >= 1) {
            $awarded = array_merge($awarded, $this->award($user, 'first_quiz'));
        }
        if ($completedAttempts >= 5) {
            $awarded = array_merge($awarded, $this->award($user, 'quiz_5'));
        }
        if ($completedAttempts >= 10) {
            $awarded = array_merge($awarded, $this->award($user, 'quiz_10'));
        }
        if ($completedAttempts >= 25) {
            $awarded = array_merge($awarded, $this->award($user, 'quiz_25'));
        }
        if ($perfectAttempts >= 1) {
            $awarded = array_merge($awarded, $this->award($user, 'perfect_score'));
        }

        return $awarded;
    }

    private function award(User $user, string $badgeCode): array
    {
        if ($this->userBadgeRepo->hasBadge($user, $badgeCode)) {
            return [];
        }

        $badge = $this->badgeRepo->findOneBy(['code' => $badgeCode]);
        if (!$badge) {
            return [];
        }

        $userBadge = new UserBadge();
        $userBadge->setUser($user);
        $userBadge->setBadge($badge);
        $this->em->persist($userBadge);
        $this->em->flush();

        return [$badge];
    }
}
