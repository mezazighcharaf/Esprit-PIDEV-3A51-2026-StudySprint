<?php

namespace App\Tests\Service;

use App\Entity\Chapter;
use App\Entity\PlanTask;
use App\Entity\RevisionPlan;
use App\Entity\Subject;
use App\Entity\User;
use App\Repository\RevisionPlanRepository;
use App\Service\PlanGeneratorService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class PlanGeneratorServiceTest extends TestCase
{
    private PlanGeneratorService $service;
    private EntityManagerInterface $em;
    private RevisionPlanRepository $planRepo;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->planRepo = $this->createMock(RevisionPlanRepository::class);
        $this->service = new PlanGeneratorService($this->em, $this->planRepo);
    }

    public function testGeneratePlanCreatesActivePlan(): void
    {
        $user = $this->createMock(User::class);
        $subject = $this->createSubjectWithChapters('Maths', ['Intégrales', 'Dérivées']);

        $this->em->expects($this->atLeastOnce())->method('persist');

        $plan = $this->service->generatePlan(
            $user,
            $subject,
            new \DateTimeImmutable('2026-03-01'),
            new \DateTimeImmutable('2026-03-07'),
            2,
            false
        );

        $this->assertInstanceOf(RevisionPlan::class, $plan);
        $this->assertEquals(RevisionPlan::STATUS_ACTIVE, $plan->getStatus());
        $this->assertStringContainsString('Maths', $plan->getTitle());
        $this->assertFalse($plan->isGeneratedByAi());
    }

    public function testGeneratePlanWithNoChaptersCreatesNoTasks(): void
    {
        $user = $this->createMock(User::class);
        $subject = $this->createSubjectWithChapters('Vide', []);

        // Only the plan itself should be persisted (no tasks)
        $persistedObjects = [];
        $this->em->method('persist')->willReturnCallback(function ($obj) use (&$persistedObjects) {
            $persistedObjects[] = $obj;
        });

        $plan = $this->service->generatePlan(
            $user,
            $subject,
            new \DateTimeImmutable('2026-03-01'),
            new \DateTimeImmutable('2026-03-07'),
            2,
            false
        );

        $this->assertInstanceOf(RevisionPlan::class, $plan);

        $tasks = array_filter($persistedObjects, fn($o) => $o instanceof PlanTask);
        $this->assertCount(0, $tasks);
    }

    public function testGeneratePlanSkipsWeekends(): void
    {
        $user = $this->createMock(User::class);
        $subject = $this->createSubjectWithChapters('Physique', ['Chapitre 1']);

        $persistedObjects = [];
        $this->em->method('persist')->willReturnCallback(function ($obj) use (&$persistedObjects) {
            $persistedObjects[] = $obj;
        });

        // 2026-03-02 is Monday, 2026-03-08 is Sunday -> 5 weekdays
        $plan = $this->service->generatePlan(
            $user,
            $subject,
            new \DateTimeImmutable('2026-03-02'),
            new \DateTimeImmutable('2026-03-08'),
            1,
            true // skip weekends
        );

        // Filter only PlanTask objects
        $tasks = array_filter($persistedObjects, fn($o) => $o instanceof PlanTask);

        // With 1 chapter and skipWeekends, we have 5 weekdays × 1 session = 5 slots
        // Chapter generates: REVISION + QUIZ + FLASHCARD = 3, then repeats REVISION for remaining 2 = 5 total
        $this->assertCount(5, $tasks);

        // Verify no task is on Saturday (6) or Sunday (7)
        foreach ($tasks as $task) {
            $dayOfWeek = (int) $task->getStartAt()->format('N');
            $this->assertLessThan(6, $dayOfWeek, 'Task should not be on a weekend');
        }
    }

    public function testGeneratePlanSetsCorrectDates(): void
    {
        $user = $this->createMock(User::class);
        $subject = $this->createSubjectWithChapters('Chimie', ['Atomes']);

        $start = new \DateTimeImmutable('2026-04-01');
        $end = new \DateTimeImmutable('2026-04-10');

        $this->em->method('persist');

        $plan = $this->service->generatePlan($user, $subject, $start, $end, 1, false);

        $this->assertEquals('2026-04-01', $plan->getStartDate()->format('Y-m-d'));
        $this->assertEquals('2026-04-10', $plan->getEndDate()->format('Y-m-d'));
    }

    public function testGeneratePlanCreatesCorrectTaskTypes(): void
    {
        $user = $this->createMock(User::class);
        $subject = $this->createSubjectWithChapters('Bio', ['Cellules']);

        $persistedObjects = [];
        $this->em->method('persist')->willReturnCallback(function ($obj) use (&$persistedObjects) {
            $persistedObjects[] = $obj;
        });

        // 3 days × 3 sessions = 9 slots, 1 chapter → REVISION, QUIZ, FLASHCARD + 6 extra REVISION
        $plan = $this->service->generatePlan(
            $user,
            $subject,
            new \DateTimeImmutable('2026-03-01'),
            new \DateTimeImmutable('2026-03-03'),
            3,
            false
        );

        $tasks = array_filter($persistedObjects, fn($o) => $o instanceof PlanTask);
        $types = array_map(fn($t) => $t->getTaskType(), $tasks);

        $this->assertContains(PlanTask::TYPE_REVISION, $types);
        $this->assertContains(PlanTask::TYPE_QUIZ, $types);
        $this->assertContains(PlanTask::TYPE_FLASHCARD, $types);
    }

    public function testGeneratePlanTasksAreAllTodo(): void
    {
        $user = $this->createMock(User::class);
        $subject = $this->createSubjectWithChapters('Français', ['Grammaire']);

        $persistedObjects = [];
        $this->em->method('persist')->willReturnCallback(function ($obj) use (&$persistedObjects) {
            $persistedObjects[] = $obj;
        });

        $this->service->generatePlan(
            $user,
            $subject,
            new \DateTimeImmutable('2026-03-01'),
            new \DateTimeImmutable('2026-03-02'),
            1,
            false
        );

        $tasks = array_filter($persistedObjects, fn($o) => $o instanceof PlanTask);

        foreach ($tasks as $task) {
            $this->assertEquals(PlanTask::STATUS_TODO, $task->getStatus());
        }
    }

    public function testGeneratePlanSingleDaySingleSession(): void
    {
        $user = $this->createMock(User::class);
        $subject = $this->createSubjectWithChapters('Philo', ['Intro']);

        $persistedObjects = [];
        $this->em->method('persist')->willReturnCallback(function ($obj) use (&$persistedObjects) {
            $persistedObjects[] = $obj;
        });

        $plan = $this->service->generatePlan(
            $user,
            $subject,
            new \DateTimeImmutable('2026-05-01'),
            new \DateTimeImmutable('2026-05-01'),
            1,
            false
        );

        $tasks = array_filter($persistedObjects, fn($o) => $o instanceof PlanTask);
        // 1 day × 1 session = 1 slot → 1 REVISION task
        $this->assertCount(1, $tasks);
    }

    private function createSubjectWithChapters(string $subjectName, array $chapterTitles): Subject
    {
        $subject = $this->createMock(Subject::class);
        $subject->method('getName')->willReturn($subjectName);

        $chapters = new ArrayCollection();
        foreach ($chapterTitles as $i => $title) {
            $chapter = $this->createMock(Chapter::class);
            $chapter->method('getTitle')->willReturn($title);
            $chapter->method('getOrderNo')->willReturn($i + 1);
            $chapters->add($chapter);
        }

        $subject->method('getChapters')->willReturn($chapters);

        return $subject;
    }
}
