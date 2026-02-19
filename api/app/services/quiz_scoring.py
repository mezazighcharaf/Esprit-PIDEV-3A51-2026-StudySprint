"""
Quiz Scoring Service
Exact port from Symfony QuizScoringService
"""
from datetime import datetime
from typing import Dict, List, Any, Optional
from app.models.training import Quiz, QuizAttempt, QuizAttemptAnswer


class QuizScoringService:
    """
    Score quiz attempts.

    Supports multiple question formats:
    1. Each choice has 'is_correct' flag
    2. Question has 'correct_index' field
    3. Question has 'correct_key' field
    """

    def score_attempt(
        self,
        attempt: QuizAttempt,
        answers: Dict[str, str],
        quiz: Quiz
    ) -> QuizAttempt:
        """
        Score a quiz attempt.

        Args:
            attempt: The quiz attempt to score
            answers: Dict of question_index -> selected_choice_index
            quiz: The quiz with questions

        Returns:
            Updated attempt with score
        """
        questions = quiz.questions or []

        if not questions:
            raise ValueError("Quiz has no questions")

        total_questions = len(questions)
        correct_count = 0
        attempt_answers: List[QuizAttemptAnswer] = []

        # Track answered questions to detect duplicates
        answered_indices = set()

        for q_idx_str, choice_str in answers.items():
            try:
                q_idx = int(q_idx_str)
                choice_idx = int(choice_str)
            except (ValueError, TypeError):
                continue

            # Validate question index
            if q_idx < 0 or q_idx >= total_questions:
                raise ValueError(f"Invalid question index: {q_idx}")

            # Check for duplicate answers
            if q_idx in answered_indices:
                raise ValueError(f"Duplicate answer for question {q_idx}")
            answered_indices.add(q_idx)

            question = questions[q_idx]
            is_correct = self._check_answer(question, choice_idx)

            if is_correct:
                correct_count += 1

            # Create answer record
            answer = QuizAttemptAnswer()
            answer.attempt_id = attempt.id
            answer.question_index = q_idx
            answer.selected_choice_key = str(choice_idx)
            answer.is_correct = is_correct
            attempt_answers.append(answer)

        # Calculate score as percentage
        score = (correct_count / total_questions) * 100 if total_questions > 0 else 0

        # Update attempt
        attempt.score = round(score, 2)
        attempt.correct_count = correct_count
        attempt.completed_at = datetime.utcnow()

        # Calculate duration
        if attempt.started_at:
            duration = (attempt.completed_at - attempt.started_at).total_seconds()
            attempt.duration_seconds = int(duration)

        return attempt, attempt_answers

    def _check_answer(self, question: Dict[str, Any], selected_idx: int) -> bool:
        """
        Check if the selected answer is correct.

        Supports three formats:
        1. choices[].is_correct = true
        2. question.correct_index = N
        3. question.correct_key = "key"
        """
        choices = question.get("choices", [])

        if selected_idx < 0 or selected_idx >= len(choices):
            return False

        # Format 1: Each choice has is_correct flag
        selected_choice = choices[selected_idx]
        if isinstance(selected_choice, dict) and "is_correct" in selected_choice:
            return selected_choice.get("is_correct", False)

        # Format 2: Question has correct_index
        if "correct_index" in question:
            return selected_idx == question["correct_index"]

        # Format 3: Question has correct_key
        if "correct_key" in question:
            choice_key = selected_choice.get("key") if isinstance(selected_choice, dict) else str(selected_idx)
            return choice_key == question["correct_key"]

        return False

    def get_missing_answers(self, quiz: Quiz, answers: Dict[str, str]) -> List[int]:
        """
        Get list of unanswered question indices.

        Args:
            quiz: The quiz
            answers: Submitted answers

        Returns:
            List of missing question indices
        """
        questions = quiz.questions or []
        total = len(questions)
        answered = set()

        for q_idx_str in answers.keys():
            try:
                answered.add(int(q_idx_str))
            except ValueError:
                continue

        return [i for i in range(total) if i not in answered]

    def get_detailed_results(
        self,
        attempt: QuizAttempt,
        quiz: Quiz,
        answers: List[QuizAttemptAnswer],
        passing_score: float = 50.0
    ) -> Dict[str, Any]:
        """
        Get detailed results for a completed attempt.

        Args:
            attempt: The completed attempt
            quiz: The quiz
            answers: The attempt answers
            passing_score: Minimum score to pass (default 50%)

        Returns:
            Detailed results dict
        """
        questions = quiz.questions or []
        score = float(attempt.score) if attempt.score else 0

        details = []
        for answer in answers:
            q_idx = answer.question_index
            if q_idx < len(questions):
                question = questions[q_idx]
                correct_idx = self._get_correct_index(question)

                details.append({
                    "question_index": q_idx,
                    "question_text": question.get("text", ""),
                    "user_answer": int(answer.selected_choice_key) if answer.selected_choice_key else None,
                    "correct_answer": correct_idx,
                    "is_correct": answer.is_correct,
                    "explanation": question.get("explanation")
                })

        return {
            "attempt_id": attempt.id,
            "quiz_id": attempt.quiz_id,
            "score": score,
            "correct_count": attempt.correct_count,
            "total_questions": attempt.total_questions,
            "passed": score >= passing_score,
            "duration_seconds": attempt.duration_seconds,
            "completed_at": attempt.completed_at,
            "details": details
        }

    def _get_correct_index(self, question: Dict[str, Any]) -> Optional[int]:
        """Get the correct answer index for a question."""
        # Format 1: Find choice with is_correct=True
        choices = question.get("choices", [])
        for idx, choice in enumerate(choices):
            if isinstance(choice, dict) and choice.get("is_correct"):
                return idx

        # Format 2: Use correct_index
        if "correct_index" in question:
            return question["correct_index"]

        # Format 3: Find by correct_key
        if "correct_key" in question:
            key = question["correct_key"]
            for idx, choice in enumerate(choices):
                if isinstance(choice, dict) and choice.get("key") == key:
                    return idx

        return None


# Singleton instance
quiz_scoring_service = QuizScoringService()
