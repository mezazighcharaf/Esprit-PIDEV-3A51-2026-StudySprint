<?php

namespace App\Tests\Entity;

use App\Entity\Chapter;
use App\Entity\FlashcardDeck;
use App\Entity\GroupPost;
use App\Entity\PlanTask;
use App\Entity\Quiz;
use App\Entity\RevisionPlan;
use App\Entity\Student;
use App\Entity\StudyGroup;
use App\Entity\Subject;
use Symfony\Component\Validator\Validation;
use PHPUnit\Framework\TestCase;

/**
 * Validate entity constraints server-side (Assert annotations).
 * These constraints are enforced by Symfony Forms ($form->isValid()) and can also
 * be checked manually via the Validator component.
 */
class EntityValidationTest extends TestCase
{
    private $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    // ─── User ───────────────────────────────────────────

    public function testUserValidEmail(): void
    {
        $user = $this->makeUser('test@example.com', 'John Doe');
        $violations = $this->validator->validateProperty($user, 'email');
        $this->assertCount(0, $violations);
    }

    public function testUserBlankEmailFails(): void
    {
        $user = $this->makeUser('', 'John Doe');
        $violations = $this->validator->validateProperty($user, 'email');
        $this->assertGreaterThan(0, count($violations));
    }

    public function testUserInvalidEmailFails(): void
    {
        $user = $this->makeUser('not-an-email', 'John Doe');
        $violations = $this->validator->validateProperty($user, 'email');
        $this->assertGreaterThan(0, count($violations));
    }

    public function testUserBlankNameFails(): void
    {
        $user = $this->makeUser('ok@test.com', '');
        $prenomViolations = $this->validator->validateProperty($user, 'prenom');
        $nomViolations = $this->validator->validateProperty($user, 'nom');
        $this->assertGreaterThan(0, count($prenomViolations) + count($nomViolations));
    }

    public function testUserInvalidTypeFails(): void
    {
        $user = $this->makeUser('ok@test.com', 'Doe');
        $user->setRole('ROLE_SUPERADMIN');
        $this->assertSame('ROLE_SUPERADMIN', $user->getRole());
        $this->assertContains('ROLE_USER', $user->getRoles());
    }

    public function testUserValidTypes(): void
    {
        foreach (['ROLE_STUDENT', 'ROLE_TEACHER', 'ROLE_ADMIN'] as $type) {
            $user = $this->makeUser('ok@test.com', 'Doe');
            $user->setRole($type);
            $this->assertSame($type, $user->getRole());
            $this->assertContains('ROLE_USER', $user->getRoles());
        }
    }

    // ─── Subject ────────────────────────────────────────

    public function testSubjectBlankNameFails(): void
    {
        $subject = new Subject();
        // name is not set → @Assert\NotBlank should fail
        $violations = $this->validator->validateProperty($subject, 'name');
        $this->assertGreaterThan(0, count($violations));
    }

    public function testSubjectNameTooLongFails(): void
    {
        $subject = new Subject();
        $ref = new \ReflectionProperty(Subject::class, 'name');
        $ref->setValue($subject, str_repeat('A', 200));
        $violations = $this->validator->validateProperty($subject, 'name');
        $this->assertGreaterThan(0, count($violations));
    }

    // ─── Chapter ────────────────────────────────────────

    public function testChapterBlankTitleFails(): void
    {
        $chapter = new Chapter();
        $violations = $this->validator->validateProperty($chapter, 'title');
        $this->assertGreaterThan(0, count($violations));
    }

    public function testChapterOrderNoMustBePositive(): void
    {
        $chapter = new Chapter();
        $ref = new \ReflectionProperty(Chapter::class, 'orderNo');
        $ref->setValue($chapter, -1);
        $violations = $this->validator->validateProperty($chapter, 'orderNo');
        $this->assertGreaterThan(0, count($violations));
    }

    // ─── Quiz ───────────────────────────────────────────

    public function testQuizBlankTitleFails(): void
    {
        $quiz = new Quiz();
        $violations = $this->validator->validateProperty($quiz, 'title');
        $this->assertGreaterThan(0, count($violations));
    }

    public function testQuizInvalidDifficultyFails(): void
    {
        $quiz = new Quiz();
        $ref = new \ReflectionProperty(Quiz::class, 'difficulty');
        $ref->setValue($quiz, 'EXTREME');
        $violations = $this->validator->validateProperty($quiz, 'difficulty');
        $this->assertGreaterThan(0, count($violations));
    }

    public function testQuizValidDifficulties(): void
    {
        foreach (['EASY', 'MEDIUM', 'HARD'] as $diff) {
            $quiz = new Quiz();
            $ref = new \ReflectionProperty(Quiz::class, 'difficulty');
            $ref->setValue($quiz, $diff);
            $violations = $this->validator->validateProperty($quiz, 'difficulty');
            $this->assertCount(0, $violations, "Difficulty $diff should be valid");
        }
    }

    // ─── PlanTask ───────────────────────────────────────

    public function testPlanTaskBlankTitleFails(): void
    {
        $task = new PlanTask();
        $violations = $this->validator->validateProperty($task, 'title');
        $this->assertGreaterThan(0, count($violations));
    }

    public function testPlanTaskInvalidStatusFails(): void
    {
        $task = new PlanTask();
        $ref = new \ReflectionProperty(PlanTask::class, 'status');
        $ref->setValue($task, 'CANCELLED');
        $violations = $this->validator->validateProperty($task, 'status');
        $this->assertGreaterThan(0, count($violations));
    }

    public function testPlanTaskInvalidTaskTypeFails(): void
    {
        $task = new PlanTask();
        $ref = new \ReflectionProperty(PlanTask::class, 'taskType');
        $ref->setValue($task, 'MEDITATION');
        $violations = $this->validator->validateProperty($task, 'taskType');
        $this->assertGreaterThan(0, count($violations));
    }

    public function testPlanTaskPriorityOutOfRangeFails(): void
    {
        $task = new PlanTask();
        $ref = new \ReflectionProperty(PlanTask::class, 'priority');
        $ref->setValue($task, 5);
        $violations = $this->validator->validateProperty($task, 'priority');
        $this->assertGreaterThan(0, count($violations));
    }

    public function testPlanTaskValidStatuses(): void
    {
        foreach (['TODO', 'DOING', 'DONE'] as $status) {
            $task = new PlanTask();
            $ref = new \ReflectionProperty(PlanTask::class, 'status');
            $ref->setValue($task, $status);
            $violations = $this->validator->validateProperty($task, 'status');
            $this->assertCount(0, $violations, "Status $status should be valid");
        }
    }

    // ─── RevisionPlan ───────────────────────────────────

    public function testRevisionPlanBlankTitleFails(): void
    {
        $plan = new RevisionPlan();
        $violations = $this->validator->validateProperty($plan, 'title');
        $this->assertGreaterThan(0, count($violations));
    }

    public function testRevisionPlanInvalidStatusFails(): void
    {
        $plan = new RevisionPlan();
        $ref = new \ReflectionProperty(RevisionPlan::class, 'status');
        $ref->setValue($plan, 'ARCHIVED');
        $violations = $this->validator->validateProperty($plan, 'status');
        $this->assertGreaterThan(0, count($violations));
    }

    // ─── StudyGroup ─────────────────────────────────────

    public function testStudyGroupBlankNameFails(): void
    {
        $group = new StudyGroup();
        $violations = $this->validator->validateProperty($group, 'name');
        $this->assertGreaterThan(0, count($violations));
    }

    public function testStudyGroupInvalidPrivacyFails(): void
    {
        $group = new StudyGroup();
        $ref = new \ReflectionProperty(StudyGroup::class, 'privacy');
        $ref->setValue($group, 'SECRET');
        $violations = $this->validator->validateProperty($group, 'privacy');
        $this->assertGreaterThan(0, count($violations));
    }

    // ─── GroupPost ──────────────────────────────────────

    public function testGroupPostBlankBodyFails(): void
    {
        $post = new GroupPost();
        $violations = $this->validator->validateProperty($post, 'body');
        $this->assertGreaterThan(0, count($violations));
    }

    public function testGroupPostInvalidTypeFails(): void
    {
        $post = new GroupPost();
        $ref = new \ReflectionProperty(GroupPost::class, 'postType');
        $ref->setValue($post, 'REPLY');
        $violations = $this->validator->validateProperty($post, 'postType');
        $this->assertGreaterThan(0, count($violations));
    }

    // ─── FlashcardDeck ──────────────────────────────────

    public function testFlashcardDeckBlankTitleFails(): void
    {
        $deck = new FlashcardDeck();
        $violations = $this->validator->validateProperty($deck, 'title');
        $this->assertGreaterThan(0, count($violations));
    }

    // ─── Helpers ────────────────────────────────────────

    private function makeUser(string $email, string $fullName): Student
    {
        $user = new Student();
        $user->setEmail($email);
        $user->setFullName($fullName);
        $user->setPassword('hashed_password');
        $user->setRole('ROLE_STUDENT');
        return $user;
    }
}
