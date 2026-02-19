<?php

namespace App\Tests\Service;

use App\Entity\Flashcard;
use App\Entity\FlashcardReviewState;
use App\Entity\User;
use App\Service\Sm2SchedulerService;
use PHPUnit\Framework\TestCase;

class Sm2SchedulerServiceTest extends TestCase
{
    private Sm2SchedulerService $service;

    protected function setUp(): void
    {
        $this->service = new Sm2SchedulerService();
    }

    public function testApplyReviewGood(): void
    {
        $state = $this->createInitialState();

        // First review with "Good" (quality = 4)
        $this->service->applyReview($state, Sm2SchedulerService::QUALITY_GOOD);

        $this->assertEquals(1, $state->getRepetitions());
        $this->assertEquals(1, $state->getIntervalDays());
        $this->assertGreaterThanOrEqual(Sm2SchedulerService::MIN_EASE_FACTOR, $state->getEaseFactor());
        $this->assertNotNull($state->getLastReviewedAt());
    }

    public function testApplyReviewGoodSecondTime(): void
    {
        $state = $this->createInitialState();

        // First review
        $this->service->applyReview($state, Sm2SchedulerService::QUALITY_GOOD);

        // Second review
        $this->service->applyReview($state, Sm2SchedulerService::QUALITY_GOOD);

        $this->assertEquals(2, $state->getRepetitions());
        $this->assertEquals(6, $state->getIntervalDays()); // SM-2: second interval is 6 days
    }

    public function testApplyReviewGoodThirdTime(): void
    {
        $state = $this->createInitialState();

        // Three consecutive "Good" reviews
        $this->service->applyReview($state, Sm2SchedulerService::QUALITY_GOOD);
        $this->service->applyReview($state, Sm2SchedulerService::QUALITY_GOOD);
        $this->service->applyReview($state, Sm2SchedulerService::QUALITY_GOOD);

        $this->assertEquals(3, $state->getRepetitions());
        // Third interval = previous interval * ease factor = 6 * 2.5 = 15 (approximately)
        $this->assertGreaterThan(6, $state->getIntervalDays());
    }

    public function testApplyReviewEasy(): void
    {
        $state = $this->createInitialState();

        // "Easy" (quality = 5) should increase ease factor
        $initialEf = $state->getEaseFactor();
        $this->service->applyReview($state, Sm2SchedulerService::QUALITY_EASY);

        $this->assertEquals(1, $state->getRepetitions());
        $this->assertGreaterThan($initialEf, $state->getEaseFactor());
    }

    public function testApplyReviewAgainResetsRepetitions(): void
    {
        $state = $this->createInitialState();

        // Build up some repetitions
        $this->service->applyReview($state, Sm2SchedulerService::QUALITY_GOOD);
        $this->service->applyReview($state, Sm2SchedulerService::QUALITY_GOOD);

        $this->assertEquals(2, $state->getRepetitions());
        $this->assertEquals(6, $state->getIntervalDays());

        // "Again" (quality = 0) should reset
        $this->service->applyReview($state, Sm2SchedulerService::QUALITY_AGAIN);

        $this->assertEquals(0, $state->getRepetitions());
        $this->assertEquals(1, $state->getIntervalDays());
    }

    public function testApplyReviewHardDecreasesEaseFactor(): void
    {
        $state = $this->createInitialState();
        $state->setEaseFactor(2.5);

        // "Hard" (quality = 3) should decrease ease factor
        $initialEf = $state->getEaseFactor();
        $this->service->applyReview($state, Sm2SchedulerService::QUALITY_HARD);

        $this->assertLessThan($initialEf, $state->getEaseFactor());
        $this->assertEquals(1, $state->getRepetitions()); // Still successful
    }

    public function testEaseFactorNeverGoesBelow1Point3(): void
    {
        $state = $this->createInitialState();
        $state->setEaseFactor(Sm2SchedulerService::MIN_EASE_FACTOR);

        // Multiple "Again" reviews shouldn't lower EF below minimum
        for ($i = 0; $i < 10; $i++) {
            $this->service->applyReview($state, Sm2SchedulerService::QUALITY_AGAIN);
        }

        $this->assertGreaterThanOrEqual(Sm2SchedulerService::MIN_EASE_FACTOR, $state->getEaseFactor());
    }

    public function testButtonToQuality(): void
    {
        $this->assertEquals(0, $this->service->buttonToQuality('again'));
        $this->assertEquals(3, $this->service->buttonToQuality('hard'));
        $this->assertEquals(4, $this->service->buttonToQuality('good'));
        $this->assertEquals(5, $this->service->buttonToQuality('easy'));

        // Case insensitive
        $this->assertEquals(4, $this->service->buttonToQuality('GOOD'));
        $this->assertEquals(4, $this->service->buttonToQuality('Good'));
    }

    public function testButtonToQualityInvalidButton(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid button');

        $this->service->buttonToQuality('invalid');
    }

    public function testApplyReviewInvalidQuality(): void
    {
        $state = $this->createInitialState();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Quality must be between 0 and 5');

        $this->service->applyReview($state, 6);
    }

    public function testApplyReviewNegativeQuality(): void
    {
        $state = $this->createInitialState();

        $this->expectException(\InvalidArgumentException::class);

        $this->service->applyReview($state, -1);
    }

    public function testCreateInitialState(): void
    {
        $user = $this->createMock(User::class);
        $flashcard = $this->createMock(Flashcard::class);

        $state = $this->service->createInitialState($user, $flashcard);

        $this->assertSame($user, $state->getUser());
        $this->assertSame($flashcard, $state->getFlashcard());
        $this->assertEquals(0, $state->getRepetitions());
        $this->assertEquals(1, $state->getIntervalDays());
        $this->assertEquals(Sm2SchedulerService::DEFAULT_EASE_FACTOR, $state->getEaseFactor());
        $this->assertTrue($state->isDue());
    }

    public function testGetNextReviewDates(): void
    {
        $state = $this->createInitialState();

        $dates = $this->service->getNextReviewDates($state);

        $this->assertArrayHasKey('again', $dates);
        $this->assertArrayHasKey('hard', $dates);
        $this->assertArrayHasKey('good', $dates);
        $this->assertArrayHasKey('easy', $dates);

        // All dates should be in the future or today
        $today = new \DateTimeImmutable('today');
        foreach ($dates as $date) {
            $this->assertInstanceOf(\DateTimeImmutable::class, $date);
            $this->assertGreaterThanOrEqual($today, $date);
        }
    }

    public function testDueAtUpdatedCorrectly(): void
    {
        $state = $this->createInitialState();

        $this->service->applyReview($state, Sm2SchedulerService::QUALITY_GOOD);

        $expectedDue = (new \DateTimeImmutable())->modify('+' . $state->getIntervalDays() . ' days');

        $this->assertEquals(
            $expectedDue->format('Y-m-d'),
            $state->getDueAt()->format('Y-m-d')
        );
    }

    private function createInitialState(): FlashcardReviewState
    {
        $user = $this->createMock(User::class);
        $flashcard = $this->createMock(Flashcard::class);

        return $this->service->createInitialState($user, $flashcard);
    }
}
