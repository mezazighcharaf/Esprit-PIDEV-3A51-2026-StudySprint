<?php

namespace App\Service;

use App\Entity\Quiz;
use App\Entity\QuizAttempt;
use App\Entity\QuizAttemptAnswer;

class QuizScoringService
{
    /**
     * Score a quiz attempt based on submitted answers.
     *
     * @param QuizAttempt $attempt The attempt to score
     * @param array<int, string> $answers Map of questionIndex => selectedChoiceKey
     * @return QuizAttempt The scored attempt
     * @throws \InvalidArgumentException If input is invalid
     */
    public function scoreAttempt(QuizAttempt $attempt, array $answers): QuizAttempt
    {
        if ($attempt->isCompleted()) {
            throw new \InvalidArgumentException('Cette tentative est déjà terminée.');
        }

        $quiz = $attempt->getQuiz();
        $questions = $quiz->getQuestions();
        $totalQuestions = count($questions);

        if ($totalQuestions === 0) {
            throw new \InvalidArgumentException('Ce quiz ne contient aucune question.');
        }

        $correctCount = 0;
        $processedIndexes = [];

        foreach ($answers as $questionIndex => $selectedChoiceKey) {
            // Validate question index
            if (!is_int($questionIndex) || $questionIndex < 0 || $questionIndex >= $totalQuestions) {
                throw new \InvalidArgumentException(
                    sprintf('Index de question invalide: %s (quiz a %d questions)', $questionIndex, $totalQuestions)
                );
            }

            // Check for duplicate answers
            if (in_array($questionIndex, $processedIndexes, true)) {
                throw new \InvalidArgumentException(
                    sprintf('Réponse en double pour la question %d', $questionIndex)
                );
            }
            $processedIndexes[] = $questionIndex;

            $question = $questions[$questionIndex];
            $isCorrect = $this->isAnswerCorrect($question, $selectedChoiceKey);

            if ($isCorrect) {
                $correctCount++;
            }

            $answer = new QuizAttemptAnswer();
            $answer->setQuestionIndex($questionIndex);
            $answer->setSelectedChoiceKey($selectedChoiceKey);
            $answer->setIsCorrect($isCorrect);
            $attempt->addAnswer($answer);
        }

        // Calculate score as percentage
        $score = ($totalQuestions > 0) ? ($correctCount / $totalQuestions) * 100 : 0;

        $attempt->setTotalQuestions($totalQuestions);
        $attempt->setCorrectCount($correctCount);
        $attempt->setScore(round($score, 2));
        $attempt->complete();

        return $attempt;
    }

    /**
     * Check if a given answer is correct for a question.
     *
     * Supports multiple question formats:
     * - {text, choices: [{key, text, isCorrect}]}
     * - {text, choices: [{text}], correctIndex: int}
     * - {text, choices: [{text}], correctKey: string}
     */
    private function isAnswerCorrect(array $question, string $selectedChoiceKey): bool
    {
        $choices = $question['choices'] ?? [];

        if (empty($choices)) {
            return false;
        }

        // Format 1: Each choice has isCorrect flag
        foreach ($choices as $index => $choice) {
            $choiceKey = $choice['key'] ?? (string) $index;

            if ($choiceKey === $selectedChoiceKey) {
                // Check if this choice is marked as correct
                if (isset($choice['isCorrect'])) {
                    return (bool) $choice['isCorrect'];
                }
            }
        }

        // Format 2: Question has correctIndex
        if (isset($question['correctIndex'])) {
            return $selectedChoiceKey === (string) $question['correctIndex'];
        }

        // Format 3: Question has correctKey or correct_key
        if (isset($question['correctKey'])) {
            return $selectedChoiceKey === $question['correctKey'];
        }
        if (isset($question['correct_key'])) {
            return $selectedChoiceKey === $question['correct_key'];
        }

        // Format 4: First choice is correct by default (fallback)
        return $selectedChoiceKey === '0';
    }

    /**
     * Validate that all questions have been answered.
     *
     * @param Quiz $quiz
     * @param array<int, string> $answers
     * @return array List of missing question indexes
     */
    public function getMissingAnswers(Quiz $quiz, array $answers): array
    {
        $questions = $quiz->getQuestions();
        $missing = [];

        for ($i = 0; $i < count($questions); $i++) {
            if (!isset($answers[$i])) {
                $missing[] = $i;
            }
        }

        return $missing;
    }

    /**
     * Get detailed results for a completed attempt.
     *
     * @return array{
     *     score: float,
     *     correctCount: int,
     *     totalQuestions: int,
     *     percentage: float,
     *     passed: bool,
     *     details: array
     * }
     */
    public function getDetailedResults(QuizAttempt $attempt, float $passingScore = 50.0): array
    {
        $questions = $attempt->getQuiz()->getQuestions();
        $answersMap = [];

        foreach ($attempt->getAnswers() as $answer) {
            $answersMap[$answer->getQuestionIndex()] = $answer;
        }

        $details = [];
        foreach ($questions as $index => $question) {
            $answer = $answersMap[$index] ?? null;
            $details[] = [
                'questionIndex' => $index,
                'questionText' => $question['text'] ?? 'Question ' . ($index + 1),
                'answered' => $answer !== null,
                'selectedChoice' => $answer?->getSelectedChoiceKey(),
                'isCorrect' => $answer?->isCorrect() ?? false,
                'choices' => $question['choices'] ?? [],
            ];
        }

        $score = $attempt->getScore() ?? 0;

        return [
            'score' => $score,
            'correctCount' => $attempt->getCorrectCount(),
            'totalQuestions' => $attempt->getTotalQuestions(),
            'percentage' => $score,
            'passed' => $score >= $passingScore,
            'duration' => $attempt->getDurationSeconds(),
            'details' => $details,
        ];
    }
}
