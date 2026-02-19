<?php

namespace App\Service;

use App\Entity\Flashcard;
use App\Entity\FlashcardReviewState;
use App\Entity\User;

/**
 * SM-2 (SuperMemo 2) Spaced Repetition Algorithm Implementation.
 *
 * Quality ratings:
 * - 0: Complete blackout (Again)
 * - 1: Incorrect response, but upon seeing the correct answer it felt familiar
 * - 2: Incorrect response, but the correct answer seemed easy to recall
 * - 3: Correct response with serious difficulty (Hard)
 * - 4: Correct response after hesitation (Good)
 * - 5: Perfect response (Easy)
 *
 * Button mapping:
 * - Again = 0
 * - Hard = 3
 * - Good = 4
 * - Easy = 5
 */
class Sm2SchedulerService
{
    public const QUALITY_AGAIN = 0;
    public const QUALITY_HARD = 3;
    public const QUALITY_GOOD = 4;
    public const QUALITY_EASY = 5;

    public const MIN_EASE_FACTOR = 1.3;
    public const DEFAULT_EASE_FACTOR = 2.5;

    /**
     * Apply SM-2 algorithm to update review state based on quality rating.
     *
     * @param FlashcardReviewState $state The current review state
     * @param int $quality Quality rating (0-5)
     * @return FlashcardReviewState The updated state
     */
    public function applyReview(FlashcardReviewState $state, int $quality): FlashcardReviewState
    {
        $this->validateQuality($quality);

        $ef = $state->getEaseFactor();
        $repetitions = $state->getRepetitions();
        $interval = $state->getIntervalDays();

        // Determine new interval and repetitions
        if ($quality < 3) {
            // Failed review - reset WITHOUT changing EF (SM-2 spec)
            $newRepetitions = 0;
            $newInterval = 1;
            $newEf = $ef; // Keep current EF unchanged
        } else {
            // Successful review
            $newRepetitions = $repetitions + 1;

            // Update ease factor using SM-2 formula (only for quality >= 3)
            $newEf = $ef + (0.1 - (5 - $quality) * (0.08 + (5 - $quality) * 0.02));
            $newEf = max(self::MIN_EASE_FACTOR, $newEf);

            if ($newRepetitions === 1) {
                $newInterval = 1;
            } elseif ($newRepetitions === 2) {
                $newInterval = 6;
            } else {
                $newInterval = (int) round($interval * $newEf);
            }
        }

        // Update state
        $state->setEaseFactor($newEf);
        $state->setRepetitions($newRepetitions);
        $state->setIntervalDays($newInterval);
        $state->setLastReviewedAt(new \DateTimeImmutable());
        $state->setDueAt(new \DateTimeImmutable("+{$newInterval} days"));

        return $state;
    }

    /**
     * Create initial review state for a new flashcard.
     */
    public function createInitialState(User $user, Flashcard $flashcard): FlashcardReviewState
    {
        $state = new FlashcardReviewState();
        $state->setUser($user);
        $state->setFlashcard($flashcard);
        $state->setEaseFactor(self::DEFAULT_EASE_FACTOR);
        $state->setRepetitions(0);
        $state->setIntervalDays(1);
        $state->setDueAt(new \DateTimeImmutable('today'));

        return $state;
    }

    /**
     * Convert button name to quality rating.
     */
    public function buttonToQuality(string $button): int
    {
        return match (strtolower($button)) {
            'again' => self::QUALITY_AGAIN,
            'hard' => self::QUALITY_HARD,
            'good' => self::QUALITY_GOOD,
            'easy' => self::QUALITY_EASY,
            default => throw new \InvalidArgumentException("Invalid button: $button"),
        };
    }

    /**
     * Get estimated next review dates for each button choice.
     *
     * @return array<string, \DateTimeImmutable>
     */
    public function getNextReviewDates(FlashcardReviewState $state): array
    {
        $dates = [];
        $buttons = ['again', 'hard', 'good', 'easy'];

        foreach ($buttons as $button) {
            $quality = $this->buttonToQuality($button);
            $clonedState = clone $state;
            $this->applyReview($clonedState, $quality);
            $dates[$button] = $clonedState->getDueAt();
        }

        return $dates;
    }

    /**
     * Validate quality rating.
     */
    private function validateQuality(int $quality): void
    {
        if ($quality < 0 || $quality > 5) {
            throw new \InvalidArgumentException(
                "Quality must be between 0 and 5, got: $quality"
            );
        }
    }

    /**
     * Calculate retention rate based on review history.
     */
    public function calculateRetention(FlashcardReviewState $state): float
    {
        $repetitions = $state->getRepetitions();
        $ef = $state->getEaseFactor();

        if ($repetitions === 0) {
            return 0.0;
        }

        // Simplified retention estimate based on ease factor and repetitions
        $retention = min(100, 50 + ($repetitions * 10) + (($ef - self::MIN_EASE_FACTOR) * 20));
        return round($retention, 1);
    }
}
