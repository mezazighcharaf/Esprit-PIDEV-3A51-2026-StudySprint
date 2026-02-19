"""
SM-2 Spaced Repetition Algorithm Service
Exact port from Symfony Sm2SchedulerService
"""
from datetime import datetime, date, timedelta
from typing import Dict
from app.models.flashcard import FlashcardReviewState, Flashcard
from app.models.user import User


class SM2SchedulerService:
    """
    SuperMemo 2 (SM-2) Spaced Repetition Algorithm

    Quality ratings:
    - 0: Complete blackout, no recall
    - 1: Incorrect, but familiar
    - 2: Incorrect, but easy to recall
    - 3: Correct with difficulty (Hard)
    - 4: Correct after hesitation (Good)
    - 5: Perfect response (Easy)
    """

    MIN_EASE_FACTOR = 1.3
    DEFAULT_EASE_FACTOR = 2.5

    # Button to quality mapping
    BUTTON_QUALITY_MAP = {
        "again": 0,
        "hard": 3,
        "good": 4,
        "easy": 5
    }

    def apply_review(self, state: FlashcardReviewState, quality: int) -> FlashcardReviewState:
        """
        Apply SM-2 algorithm after a review.

        Args:
            state: Current review state
            quality: Quality of response (0-5)

        Returns:
            Updated review state
        """
        if quality < 0 or quality > 5:
            raise ValueError(f"Quality must be between 0 and 5, got {quality}")

        # Get current ease factor
        ef = float(state.ease_factor) if state.ease_factor else self.DEFAULT_EASE_FACTOR

        # Calculate new ease factor using SM-2 formula
        # EF' = EF + (0.1 - (5 - q) * (0.08 + (5 - q) * 0.02))
        ef_delta = 0.1 - (5 - quality) * (0.08 + (5 - quality) * 0.02)
        new_ef = ef + ef_delta

        # Enforce minimum ease factor
        if new_ef < self.MIN_EASE_FACTOR:
            new_ef = self.MIN_EASE_FACTOR

        state.ease_factor = new_ef

        # Calculate interval based on quality
        if quality < 3:
            # Failed - reset to beginning
            state.repetitions = 0
            state.interval_days = 1
        else:
            # Passed
            repetitions = state.repetitions or 0

            if repetitions == 0:
                # First successful review
                state.interval_days = 1
            elif repetitions == 1:
                # Second successful review
                state.interval_days = 6
            else:
                # Subsequent reviews: interval * EF
                current_interval = state.interval_days or 1
                state.interval_days = int(round(current_interval * new_ef))

            state.repetitions = repetitions + 1

        # Update timestamps
        state.last_reviewed_at = datetime.utcnow()
        state.due_at = date.today() + timedelta(days=state.interval_days)

        return state

    def create_initial_state(self, user: User, flashcard: Flashcard) -> FlashcardReviewState:
        """
        Create initial review state for a new card.

        Args:
            user: The user
            flashcard: The flashcard

        Returns:
            New review state (due today)
        """
        state = FlashcardReviewState()
        state.user_id = user.id
        state.flashcard_id = flashcard.id
        state.repetitions = 0
        state.interval_days = 1
        state.ease_factor = self.DEFAULT_EASE_FACTOR
        state.due_at = date.today()
        state.last_reviewed_at = None
        return state

    def button_to_quality(self, button: str) -> int:
        """
        Convert button name to quality rating.

        Args:
            button: Button name (again, hard, good, easy)

        Returns:
            Quality rating (0-5)
        """
        button_lower = button.lower()
        if button_lower not in self.BUTTON_QUALITY_MAP:
            raise ValueError(f"Invalid button: {button}. Must be one of: {list(self.BUTTON_QUALITY_MAP.keys())}")
        return self.BUTTON_QUALITY_MAP[button_lower]

    def get_next_review_dates(self, state: FlashcardReviewState) -> Dict[str, date]:
        """
        Predict next review dates for each possible response.

        Args:
            state: Current review state

        Returns:
            Dict mapping button names to predicted due dates
        """
        results = {}

        for button, quality in self.BUTTON_QUALITY_MAP.items():
            # Simulate the review
            ef = float(state.ease_factor) if state.ease_factor else self.DEFAULT_EASE_FACTOR
            repetitions = state.repetitions or 0
            interval = state.interval_days or 1

            # Calculate new EF
            ef_delta = 0.1 - (5 - quality) * (0.08 + (5 - quality) * 0.02)
            new_ef = max(ef + ef_delta, self.MIN_EASE_FACTOR)

            # Calculate new interval
            if quality < 3:
                new_interval = 1
            else:
                if repetitions == 0:
                    new_interval = 1
                elif repetitions == 1:
                    new_interval = 6
                else:
                    new_interval = int(round(interval * new_ef))

            results[button] = date.today() + timedelta(days=new_interval)

        return results

    def calculate_retention(self, state: FlashcardReviewState) -> float:
        """
        Estimate retention percentage based on SM-2 state.

        Uses forgetting curve approximation.

        Args:
            state: Review state

        Returns:
            Estimated retention (0.0 to 1.0)
        """
        if state.last_reviewed_at is None:
            return 1.0  # New card, assume full retention

        days_since_review = (date.today() - state.due_at).days

        if days_since_review <= 0:
            return 0.95  # Not yet due, high retention

        # Simple forgetting curve: R = e^(-t/S)
        # S = stability (approximated by interval * EF)
        ef = float(state.ease_factor) if state.ease_factor else self.DEFAULT_EASE_FACTOR
        stability = (state.interval_days or 1) * ef

        import math
        retention = math.exp(-days_since_review / stability)

        return max(0.0, min(1.0, retention))


# Singleton instance
sm2_service = SM2SchedulerService()
