<?php

namespace App\Tests\Service;

use App\Service\AiGatewayService;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class AiGatewayServiceTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private AiGatewayService $service;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->service = new AiGatewayService($this->httpClient, 'http://localhost:8001');
    }

    // =========================================================================
    // generateQuiz
    // =========================================================================

    public function testGenerateQuizSuccess(): void
    {
        $expected = [
            'quiz_id' => 42,
            'title' => 'Quiz IA - Maths',
            'questions_count' => 5,
            'difficulty' => 'MEDIUM',
            'ai_log_id' => 1,
            'message' => 'Quiz généré avec succès via ollama',
        ];

        $this->mockResponse('POST', '/api/v1/ai/generate/quiz', $expected, 120);

        $result = $this->service->generateQuiz(1, 10, 3, 5, 'MEDIUM', 'Intégrales');

        $this->assertSame(42, $result['quiz_id']);
        $this->assertSame(5, $result['questions_count']);
        $this->assertSame('MEDIUM', $result['difficulty']);
    }

    public function testGenerateQuizMinimalParams(): void
    {
        $expected = ['quiz_id' => 1, 'title' => 'Quiz', 'questions_count' => 5, 'difficulty' => 'MEDIUM', 'ai_log_id' => 1, 'message' => 'ok'];

        $this->mockResponse('POST', '/api/v1/ai/generate/quiz', $expected, 120);

        $result = $this->service->generateQuiz(1, 10, null);

        $this->assertSame(1, $result['quiz_id']);
    }

    // =========================================================================
    // generateFlashcards
    // =========================================================================

    public function testGenerateFlashcardsSuccess(): void
    {
        $expected = [
            'deck_id' => 7,
            'title' => 'Deck IA - Physique',
            'cards_count' => 10,
            'ai_log_id' => 2,
            'message' => 'Deck généré avec succès',
        ];

        $this->mockResponse('POST', '/api/v1/ai/generate/flashcards', $expected, 120);

        $result = $this->service->generateFlashcards(1, 10, null, 10, 'Quantique', true);

        $this->assertSame(7, $result['deck_id']);
        $this->assertSame(10, $result['cards_count']);
    }

    // =========================================================================
    // enhanceProfile
    // =========================================================================

    public function testEnhanceProfileSuccess(): void
    {
        $expected = [
            'suggested_bio' => 'Étudiant passionné.',
            'suggested_goals' => 'Objectifs SMART.',
            'suggested_routine' => 'Matin: cours, Soir: exercices.',
            'ai_log_id' => 3,
        ];

        $this->mockResponse('POST', '/api/v1/ai/profile/enhance', $expected, 60);

        $result = $this->service->enhanceProfile(1, 'Ma bio', 'LICENCE', 'Maths');

        $this->assertSame('Étudiant passionné.', $result['suggested_bio']);
        $this->assertSame('Objectifs SMART.', $result['suggested_goals']);
        $this->assertSame('Matin: cours, Soir: exercices.', $result['suggested_routine']);
    }

    // =========================================================================
    // summarizeChapter
    // =========================================================================

    public function testSummarizeChapterSuccess(): void
    {
        $expected = [
            'summary' => 'Résumé du chapitre.',
            'key_points' => ['Point 1', 'Point 2'],
            'tags' => ['suites', 'convergence'],
            'ai_log_id' => 4,
        ];

        $this->mockResponse('POST', '/api/v1/ai/chapter/summarize', $expected, 60);

        $result = $this->service->summarizeChapter(1, 5);

        $this->assertSame('Résumé du chapitre.', $result['summary']);
        $this->assertCount(2, $result['key_points']);
        $this->assertCount(2, $result['tags']);
    }

    // =========================================================================
    // suggestPlanOptimizations
    // =========================================================================

    public function testSuggestPlanOptimizationsSuccess(): void
    {
        $expected = [
            'suggestions' => [
                ['task_id' => 1, 'action' => 'reschedule', 'reason' => 'Mieux répartir'],
            ],
            'explanation' => 'Plan analysé.',
            'ai_log_id' => 5,
            'can_apply' => true,
        ];

        $this->mockResponse('POST', '/api/v1/ai/planning/suggest', $expected, 120);

        $result = $this->service->suggestPlanOptimizations(1, 10, 'reduce workload');

        $this->assertTrue($result['can_apply']);
        $this->assertCount(1, $result['suggestions']);
        $this->assertSame('reschedule', $result['suggestions'][0]['action']);
    }

    // =========================================================================
    // applyPlanSuggestions
    // =========================================================================

    public function testApplyPlanSuggestionsSuccess(): void
    {
        $expected = [
            'message' => '2 modifications appliquées',
            'applied_count' => 2,
            'total_suggestions' => 3,
        ];

        $this->mockResponse('POST', '/api/v1/ai/planning/apply', $expected, 30);

        $result = $this->service->applyPlanSuggestions(1, 5);

        $this->assertSame(2, $result['applied_count']);
        $this->assertSame(3, $result['total_suggestions']);
    }

    // =========================================================================
    // summarizePost
    // =========================================================================

    public function testSummarizePostSuccess(): void
    {
        $expected = [
            'summary' => 'Résumé du post.',
            'category' => 'question',
            'tags' => ['aide', 'maths'],
            'ai_log_id' => 6,
        ];

        $this->mockResponse('POST', '/api/v1/ai/post/summarize', $expected, 60);

        $result = $this->service->summarizePost(1, 42);

        $this->assertSame('question', $result['category']);
        $this->assertCount(2, $result['tags']);
    }

    // =========================================================================
    // submitFeedback
    // =========================================================================

    public function testSubmitFeedbackSuccess(): void
    {
        $expected = ['message' => 'Feedback enregistré', 'rating' => 4];

        $this->mockResponse('POST', '/api/v1/ai/feedback', $expected, 10);

        $result = $this->service->submitFeedback(1, 10, 4);

        $this->assertSame(4, $result['rating']);
    }

    // =========================================================================
    // getStats
    // =========================================================================

    public function testGetStatsSuccess(): void
    {
        $expected = [
            'total_requests' => 100,
            'success_count' => 90,
            'failed_count' => 10,
            'failure_rate' => 10.0,
            'by_feature' => ['quiz' => 40, 'flashcard' => 30],
            'avg_latency_ms' => 8500.0,
            'last_7_days_count' => 25,
            'avg_feedback_rating' => 4.2,
        ];

        $this->mockResponse('GET', '/api/v1/ai/logs/stats', $expected, 5);

        $result = $this->service->getStats();

        $this->assertSame(100, $result['total_requests']);
        $this->assertSame(90, $result['success_count']);
        $this->assertEquals(10.0, $result['failure_rate']);
    }

    // =========================================================================
    // Error handling
    // =========================================================================

    public function testServiceThrowsOnHttpError(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(500);
        $response->method('getContent')->with(false)->willReturn('{"detail":"boom"}');

        $this->httpClient->method('request')->willReturn($response);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('failed (500)');

        $this->service->generateQuiz(1, 10, null);
    }

    public function testBaseUrlTrimsTrailingSlash(): void
    {
        $service = new AiGatewayService($this->httpClient, 'http://example.com:9000/');

        $expected = ['total_requests' => 0, 'success_count' => 0, 'failed_count' => 0, 'failure_rate' => 0, 'by_feature' => [], 'avg_latency_ms' => 0, 'last_7_days_count' => 0, 'avg_feedback_rating' => null];

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getContent')->with(false)->willReturn(json_encode($expected, JSON_THROW_ON_ERROR));

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('GET', 'http://example.com:9000/api/v1/ai/logs/stats', $this->anything())
            ->willReturn($response);

        $result = $service->getStats();
        $this->assertSame(0, $result['total_requests']);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function mockResponse(string $method, string $pathSuffix, array $returnData, int $expectedTimeout): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getContent')->with(false)->willReturn(json_encode($returnData, JSON_THROW_ON_ERROR));

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with(
                $method,
                'http://localhost:8001' . $pathSuffix,
                $this->callback(function (array $options) use ($expectedTimeout) {
                    return isset($options['timeout']) && $options['timeout'] === $expectedTimeout;
                })
            )
            ->willReturn($response);
    }
}
