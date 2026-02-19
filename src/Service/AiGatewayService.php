<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Centralized service for all FastAPI AI Gateway communications.
 * Replaces hardcoded http://localhost:8001 URLs across controllers.
 */
class AiGatewayService
{
    private string $baseUrl;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        string $aiGatewayBaseUrl = 'http://localhost:8001'
    ) {
        $this->baseUrl = rtrim($aiGatewayBaseUrl, '/');
    }

    private function apiUrl(string $path): string
    {
        return $this->baseUrl . '/api/v1/ai' . $path;
    }

    /**
     * Check AI service availability.
     * @return array{ollama_available: bool, active_provider: string, available: bool}
     */
    public function getStatus(): array
    {
        $response = $this->httpClient->request('GET', $this->apiUrl('/status'), [
            'timeout' => 10,
        ]);

        return $response->toArray();
    }

    /**
     * Generate a quiz via AI.
     * @return array{quiz_id: int, title: string, questions_count: int, difficulty: string, ai_log_id: int, message: string}
     */
    public function generateQuiz(int $userId, int $subjectId, ?int $chapterId, int $numQuestions = 5, string $difficulty = 'MEDIUM', ?string $topic = null): array
    {
        $response = $this->httpClient->request('POST', $this->apiUrl('/generate/quiz'), [
            'json' => [
                'user_id' => $userId,
                'subject_id' => $subjectId,
                'chapter_id' => $chapterId,
                'num_questions' => $numQuestions,
                'difficulty' => $difficulty,
                'topic' => $topic,
            ],
            'timeout' => 120,
        ]);

        return $response->toArray();
    }

    /**
     * Generate flashcards via AI.
     * @return array{deck_id: int, title: string, cards_count: int, ai_log_id: int, message: string}
     */
    public function generateFlashcards(int $userId, int $subjectId, ?int $chapterId, int $numCards = 10, ?string $topic = null, bool $includeHints = true): array
    {
        $response = $this->httpClient->request('POST', $this->apiUrl('/generate/flashcards'), [
            'json' => [
                'user_id' => $userId,
                'subject_id' => $subjectId,
                'chapter_id' => $chapterId,
                'num_cards' => $numCards,
                'topic' => $topic,
                'include_hints' => $includeHints,
            ],
            'timeout' => 120,
        ]);

        return $response->toArray();
    }

    /**
     * Enhance user profile with AI suggestions.
     * @return array{suggested_bio: ?string, suggested_goals: ?string, suggested_routine: ?string, ai_log_id: int}
     */
    public function enhanceProfile(int $userId, ?string $currentBio = null, ?string $currentLevel = null, ?string $currentSpecialty = null, ?string $goals = null): array
    {
        $response = $this->httpClient->request('POST', $this->apiUrl('/profile/enhance'), [
            'json' => [
                'user_id' => $userId,
                'current_bio' => $currentBio,
                'current_level' => $currentLevel,
                'current_specialty' => $currentSpecialty,
                'goals' => $goals,
            ],
            'timeout' => 60,
        ]);

        return $response->toArray();
    }

    /**
     * Summarize a chapter with AI (summary, key points, tags).
     * @return array{summary: string, key_points: array, tags: array, ai_log_id: int}
     */
    public function summarizeChapter(int $userId, int $chapterId): array
    {
        $response = $this->httpClient->request('POST', $this->apiUrl('/chapter/summarize'), [
            'json' => [
                'user_id' => $userId,
                'chapter_id' => $chapterId,
            ],
            'timeout' => 60,
        ]);

        return $response->toArray();
    }

    /**
     * Get AI suggestions for plan optimization.
     * @return array{suggestions: array, explanation: string, ai_log_id: int, can_apply: bool}
     */
    public function suggestPlanOptimizations(int $userId, int $planId, ?string $optimizationGoals = null): array
    {
        $response = $this->httpClient->request('POST', $this->apiUrl('/planning/suggest'), [
            'json' => [
                'user_id' => $userId,
                'plan_id' => $planId,
                'optimization_goals' => $optimizationGoals,
            ],
            'timeout' => 120,
        ]);

        return $response->toArray();
    }

    /**
     * Apply previously generated AI suggestions to a plan.
     * @return array{message: string, applied_count: int, total_suggestions: int}
     */
    public function applyPlanSuggestions(int $userId, int $suggestionLogId): array
    {
        $response = $this->httpClient->request('POST', $this->apiUrl('/planning/apply'), [
            'json' => [
                'user_id' => $userId,
                'suggestion_log_id' => $suggestionLogId,
            ],
            'timeout' => 30,
        ]);

        return $response->toArray();
    }

    /**
     * Summarize a group post with AI.
     * @return array{summary: string, category: string, tags: array, ai_log_id: int}
     */
    public function summarizePost(int $userId, int $postId): array
    {
        $response = $this->httpClient->request('POST', $this->apiUrl('/post/summarize'), [
            'json' => [
                'user_id' => $userId,
                'post_id' => $postId,
            ],
            'timeout' => 60,
        ]);

        return $response->toArray();
    }

    /**
     * Submit user feedback for an AI generation.
     * @return array{message: string, rating: int}
     */
    public function submitFeedback(int $userId, int $logId, int $rating): array
    {
        $response = $this->httpClient->request('POST', $this->apiUrl('/feedback'), [
            'json' => [
                'user_id' => $userId,
                'log_id' => $logId,
                'rating' => $rating,
            ],
            'timeout' => 10,
        ]);

        return $response->toArray();
    }

    /**
     * Get AI usage statistics for BO monitoring.
     * @return array
     */
    public function getStats(): array
    {
        $response = $this->httpClient->request('GET', $this->apiUrl('/logs/stats'), [
            'timeout' => 5,
        ]);

        return $response->toArray();
    }
}
