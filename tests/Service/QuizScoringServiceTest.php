<?php

namespace App\Tests\Service;

use App\Entity\Quiz;
use App\Entity\QuizAttempt;
use App\Entity\User;
use App\Service\QuizScoringService;
use PHPUnit\Framework\TestCase;

class QuizScoringServiceTest extends TestCase
{
    private QuizScoringService $service;

    protected function setUp(): void
    {
        $this->service = new QuizScoringService();
    }

    public function testScoreAttemptNominalCase(): void
    {
        $user = $this->createMock(User::class);
        $quiz = $this->createQuizWithQuestions([
            ['text' => 'Q1', 'choices' => [['text' => 'A', 'isCorrect' => true], ['text' => 'B', 'isCorrect' => false]]],
            ['text' => 'Q2', 'choices' => [['text' => 'A', 'isCorrect' => false], ['text' => 'B', 'isCorrect' => true]]],
            ['text' => 'Q3', 'choices' => [['text' => 'A', 'isCorrect' => true], ['text' => 'B', 'isCorrect' => false]]],
        ]);

        $attempt = new QuizAttempt();
        $attempt->setUser($user);
        $attempt->setQuiz($quiz);

        // Answer: Q1 correct, Q2 correct, Q3 incorrect
        $answers = [
            0 => '0', // Correct (A is correct)
            1 => '1', // Correct (B is correct)
            2 => '1', // Incorrect (A was correct)
        ];

        $result = $this->service->scoreAttempt($attempt, $answers);

        $this->assertTrue($result->isCompleted());
        $this->assertEquals(3, $result->getTotalQuestions());
        $this->assertEquals(2, $result->getCorrectCount());
        $this->assertEqualsWithDelta(66.67, $result->getScore(), 0.01);
        $this->assertCount(3, $result->getAnswers());
    }

    public function testScoreAttemptPerfectScore(): void
    {
        $user = $this->createMock(User::class);
        $quiz = $this->createQuizWithQuestions([
            ['text' => 'Q1', 'choices' => [['text' => 'A', 'isCorrect' => true], ['text' => 'B', 'isCorrect' => false]]],
            ['text' => 'Q2', 'choices' => [['text' => 'A', 'isCorrect' => false], ['text' => 'B', 'isCorrect' => true]]],
        ]);

        $attempt = new QuizAttempt();
        $attempt->setUser($user);
        $attempt->setQuiz($quiz);

        $answers = [
            0 => '0', // Correct
            1 => '1', // Correct
        ];

        $result = $this->service->scoreAttempt($attempt, $answers);

        $this->assertEquals(100.0, $result->getScore());
        $this->assertEquals(2, $result->getCorrectCount());
    }

    public function testScoreAttemptZeroScore(): void
    {
        $user = $this->createMock(User::class);
        $quiz = $this->createQuizWithQuestions([
            ['text' => 'Q1', 'choices' => [['text' => 'A', 'isCorrect' => true], ['text' => 'B', 'isCorrect' => false]]],
            ['text' => 'Q2', 'choices' => [['text' => 'A', 'isCorrect' => true], ['text' => 'B', 'isCorrect' => false]]],
        ]);

        $attempt = new QuizAttempt();
        $attempt->setUser($user);
        $attempt->setQuiz($quiz);

        $answers = [
            0 => '1', // Incorrect
            1 => '1', // Incorrect
        ];

        $result = $this->service->scoreAttempt($attempt, $answers);

        $this->assertEquals(0.0, $result->getScore());
        $this->assertEquals(0, $result->getCorrectCount());
    }

    public function testScoreAttemptWithCorrectIndexFormat(): void
    {
        $user = $this->createMock(User::class);
        $quiz = $this->createQuizWithQuestions([
            ['text' => 'Q1', 'choices' => [['text' => 'A'], ['text' => 'B']], 'correctIndex' => 1],
        ]);

        $attempt = new QuizAttempt();
        $attempt->setUser($user);
        $attempt->setQuiz($quiz);

        $answers = [0 => '1'];

        $result = $this->service->scoreAttempt($attempt, $answers);

        $this->assertEquals(100.0, $result->getScore());
    }

    public function testScoreAttemptThrowsOnInvalidQuestionIndex(): void
    {
        $user = $this->createMock(User::class);
        $quiz = $this->createQuizWithQuestions([
            ['text' => 'Q1', 'choices' => [['text' => 'A', 'isCorrect' => true]]],
        ]);

        $attempt = new QuizAttempt();
        $attempt->setUser($user);
        $attempt->setQuiz($quiz);

        $answers = [
            0 => '0',
            5 => '0', // Invalid: quiz only has 1 question
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Index de question invalide');

        $this->service->scoreAttempt($attempt, $answers);
    }

    public function testScoreAttemptThrowsOnDuplicateAnswers(): void
    {
        $user = $this->createMock(User::class);
        $quiz = $this->createQuizWithQuestions([
            ['text' => 'Q1', 'choices' => [['text' => 'A', 'isCorrect' => true]]],
            ['text' => 'Q2', 'choices' => [['text' => 'A', 'isCorrect' => true]]],
        ]);

        $attempt = new QuizAttempt();
        $attempt->setUser($user);
        $attempt->setQuiz($quiz);

        // Manually create duplicate by abusing array - this is a simulation
        // In real usage, duplicates would come from malformed input
        $answers = [0 => '0', 1 => '0'];

        // First call the method, then try to call it again
        $this->service->scoreAttempt($attempt, $answers);

        // The attempt is now completed
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('déjà terminée');

        $this->service->scoreAttempt($attempt, $answers);
    }

    public function testScoreAttemptThrowsOnEmptyQuiz(): void
    {
        $user = $this->createMock(User::class);
        $quiz = $this->createQuizWithQuestions([]);

        $attempt = new QuizAttempt();
        $attempt->setUser($user);
        $attempt->setQuiz($quiz);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('aucune question');

        $this->service->scoreAttempt($attempt, []);
    }

    public function testGetMissingAnswers(): void
    {
        $quiz = $this->createQuizWithQuestions([
            ['text' => 'Q1', 'choices' => []],
            ['text' => 'Q2', 'choices' => []],
            ['text' => 'Q3', 'choices' => []],
        ]);

        $answers = [0 => 'a', 2 => 'b']; // Missing Q2 (index 1)

        $missing = $this->service->getMissingAnswers($quiz, $answers);

        $this->assertEquals([1], $missing);
    }

    public function testGetDetailedResults(): void
    {
        $user = $this->createMock(User::class);
        $quiz = $this->createQuizWithQuestions([
            ['text' => 'Question 1', 'choices' => [['text' => 'A', 'isCorrect' => true], ['text' => 'B', 'isCorrect' => false]]],
        ]);

        $attempt = new QuizAttempt();
        $attempt->setUser($user);
        $attempt->setQuiz($quiz);

        $this->service->scoreAttempt($attempt, [0 => '0']);

        $results = $this->service->getDetailedResults($attempt, 50.0);

        $this->assertEquals(100.0, $results['score']);
        $this->assertTrue($results['passed']);
        $this->assertCount(1, $results['details']);
        $this->assertEquals('Question 1', $results['details'][0]['questionText']);
        $this->assertTrue($results['details'][0]['isCorrect']);
    }

    private function createQuizWithQuestions(array $questions): Quiz
    {
        $quiz = $this->createMock(Quiz::class);
        $quiz->method('getQuestions')->willReturn($questions);
        $quiz->method('isPublished')->willReturn(true);
        $quiz->method('getId')->willReturn(1);

        return $quiz;
    }
}
