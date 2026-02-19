<?php

namespace App\Service;

use App\Entity\Chapter;
use App\Entity\PlanTask;
use App\Entity\RevisionPlan;
use App\Entity\Subject;
use App\Entity\User;
use App\Repository\RevisionPlanRepository;
use Doctrine\ORM\EntityManagerInterface;

class PlanGeneratorService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RevisionPlanRepository $planRepo,
    ) {}

    /**
     * Check if a plan already exists for this user/subject in the given period.
     *
     * @return RevisionPlan|null The overlapping plan, if any
     */
    public function findOverlappingPlan(
        User $user,
        Subject $subject,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate
    ): ?RevisionPlan {
        return $this->planRepo->createQueryBuilder('p')
            ->andWhere('p.user = :user')
            ->andWhere('p.subject = :subject')
            ->andWhere('p.startDate <= :endDate')
            ->andWhere('p.endDate >= :startDate')
            ->setParameter('user', $user)
            ->setParameter('subject', $subject)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Generate a revision plan with tasks distributed over the date range.
     *
     * @param User $user
     * @param Subject $subject
     * @param \DateTimeImmutable $startDate
     * @param \DateTimeImmutable $endDate
     * @param int $sessionsPerDay Number of study sessions per day
     * @param bool $skipWeekends Whether to skip weekends
     * @return RevisionPlan
     */
    public function generatePlan(
        User $user,
        Subject $subject,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
        int $sessionsPerDay = 2,
        bool $skipWeekends = false
    ): RevisionPlan {
        $plan = new RevisionPlan();
        $plan->setUser($user);
        $plan->setSubject($subject);
        $plan->setTitle(sprintf('Plan de révision: %s', $subject->getName()));
        $plan->setStartDate($startDate);
        $plan->setEndDate($endDate);
        $plan->setStatus(RevisionPlan::STATUS_ACTIVE);
        $plan->setGeneratedByAi(false);

        $this->em->persist($plan);

        // Get chapters for the subject
        $chapters = $subject->getChapters()->toArray();
        usort($chapters, fn(Chapter $a, Chapter $b) => $a->getOrderNo() <=> $b->getOrderNo());

        // Collect available days
        $availableDays = $this->getAvailableDays($startDate, $endDate, $skipWeekends);
        $totalSlots = count($availableDays) * $sessionsPerDay;

        if ($totalSlots === 0) {
            return $plan;
        }

        // Distribute tasks
        $taskTypes = [
            PlanTask::TYPE_REVISION,
            PlanTask::TYPE_QUIZ,
            PlanTask::TYPE_FLASHCARD,
        ];

        $slotIndex = 0;
        $sessionHours = [9, 14, 17, 19]; // Start hours for sessions

        // Create tasks for each chapter
        foreach ($chapters as $chapter) {
            if ($slotIndex >= $totalSlots) {
                break;
            }

            // Create revision task
            $task = $this->createTask(
                $plan,
                $chapter,
                PlanTask::TYPE_REVISION,
                $availableDays,
                $slotIndex,
                $sessionsPerDay,
                $sessionHours
            );
            $this->em->persist($task);
            $slotIndex++;

            // Create quiz task if we have slots left
            if ($slotIndex < $totalSlots) {
                $task = $this->createTask(
                    $plan,
                    $chapter,
                    PlanTask::TYPE_QUIZ,
                    $availableDays,
                    $slotIndex,
                    $sessionsPerDay,
                    $sessionHours
                );
                $this->em->persist($task);
                $slotIndex++;
            }

            // Create flashcard task if we have slots left
            if ($slotIndex < $totalSlots) {
                $task = $this->createTask(
                    $plan,
                    $chapter,
                    PlanTask::TYPE_FLASHCARD,
                    $availableDays,
                    $slotIndex,
                    $sessionsPerDay,
                    $sessionHours
                );
                $this->em->persist($task);
                $slotIndex++;
            }
        }

        // If we still have slots and chapters, repeat with revision tasks
        if (count($chapters) > 0) {
            $chapterIndex = 0;
            while ($slotIndex < $totalSlots) {
                $chapter = $chapters[$chapterIndex % count($chapters)];
                $task = $this->createTask(
                    $plan,
                    $chapter,
                    PlanTask::TYPE_REVISION,
                    $availableDays,
                    $slotIndex,
                    $sessionsPerDay,
                    $sessionHours,
                    'Révision supplémentaire'
                );
                $this->em->persist($task);
                $slotIndex++;
                $chapterIndex++;
            }
        }

        return $plan;
    }

    /**
     * Replace an existing plan by deleting its tasks and regenerating.
     */
    public function replacePlan(
        RevisionPlan $existingPlan,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
        int $sessionsPerDay = 2,
        bool $skipWeekends = false
    ): RevisionPlan {
        // Delete existing tasks
        foreach ($existingPlan->getTasks() as $task) {
            $this->em->remove($task);
        }

        // Update plan dates
        $existingPlan->setStartDate($startDate);
        $existingPlan->setEndDate($endDate);
        $existingPlan->setStatus(RevisionPlan::STATUS_ACTIVE);

        // Generate new tasks (similar logic to generatePlan)
        $subject = $existingPlan->getSubject();
        $chapters = $subject->getChapters()->toArray();
        usort($chapters, fn(Chapter $a, Chapter $b) => $a->getOrderNo() <=> $b->getOrderNo());

        $availableDays = $this->getAvailableDays($startDate, $endDate, $skipWeekends);
        $totalSlots = count($availableDays) * $sessionsPerDay;
        $sessionHours = [9, 14, 17, 19];
        $slotIndex = 0;

        foreach ($chapters as $chapter) {
            foreach ([PlanTask::TYPE_REVISION, PlanTask::TYPE_QUIZ, PlanTask::TYPE_FLASHCARD] as $type) {
                if ($slotIndex >= $totalSlots) break 2;
                $task = $this->createTask(
                    $existingPlan,
                    $chapter,
                    $type,
                    $availableDays,
                    $slotIndex,
                    $sessionsPerDay,
                    $sessionHours
                );
                $this->em->persist($task);
                $slotIndex++;
            }
        }

        return $existingPlan;
    }

    /**
     * @return \DateTimeImmutable[]
     */
    private function getAvailableDays(
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        bool $skipWeekends
    ): array {
        $days = [];
        $current = $start;

        while ($current <= $end) {
            $dayOfWeek = (int) $current->format('N');
            if (!$skipWeekends || ($dayOfWeek < 6)) {
                $days[] = $current;
            }
            $current = $current->modify('+1 day');
        }

        return $days;
    }

    private function createTask(
        RevisionPlan $plan,
        Chapter $chapter,
        string $type,
        array $availableDays,
        int $slotIndex,
        int $sessionsPerDay,
        array $sessionHours,
        ?string $titlePrefix = null
    ): PlanTask {
        $dayIndex = intdiv($slotIndex, $sessionsPerDay);
        $sessionIndex = $slotIndex % $sessionsPerDay;

        $day = $availableDays[$dayIndex] ?? end($availableDays);
        $hour = $sessionHours[$sessionIndex] ?? 9;

        $startAt = $day->setTime($hour, 0);
        $endAt = $startAt->modify('+1 hour');

        $typeLabels = [
            PlanTask::TYPE_REVISION => 'Révision',
            PlanTask::TYPE_QUIZ => 'Quiz',
            PlanTask::TYPE_FLASHCARD => 'Flashcards',
            PlanTask::TYPE_CUSTOM => 'Tâche',
        ];

        $title = $titlePrefix ?? sprintf('%s: %s', $typeLabels[$type] ?? $type, $chapter->getTitle());

        $task = new PlanTask();
        $task->setPlan($plan);
        $task->setTitle($title);
        $task->setTaskType($type);
        $task->setStartAt($startAt);
        $task->setEndAt($endAt);
        $task->setStatus(PlanTask::STATUS_TODO);
        $task->setPriority(2);
        $task->setNotes(sprintf('Chapitre: %s', $chapter->getTitle()));

        return $task;
    }
}
