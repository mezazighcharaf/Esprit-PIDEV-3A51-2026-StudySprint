<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Centralized service for all FastAPI AI Gateway communications.
 * Replaces hardcoded http://localhost:8001 URLs across controllers.
 */
class AiGatewayService
{
    private string $baseUrl;
    /** @var list<string> */
    private array $apiPrefixes = ['/api/v1/ai', '/api/ai', '/ai', '/api/v1', '/api', ''];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        string $aiGatewayBaseUrl = 'http://localhost:8001'
    ) {
        $this->baseUrl = rtrim($aiGatewayBaseUrl, '/');
    }

    private function apiUrl(string $path, string $prefix = '/api/v1/ai'): string
    {
        $normalizedPrefix = trim($prefix);
        if ($normalizedPrefix === '') {
            return $this->baseUrl . $path;
        }
        return $this->baseUrl . $normalizedPrefix . $path;
    }

    /**
     * @return list<string>
     */
    private function buildCandidateUrls(string $path): array
    {
        $urls = [];
        foreach ($this->apiPrefixes as $prefix) {
            $urls[] = $this->apiUrl($path, $prefix);
        }
        return $urls;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(ResponseInterface $response): array
    {
        $content = $response->getContent(false);
        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param list<string> $paths
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function requestWithPathFallback(string $method, array $paths, array $options = []): array
    {
        $errors = [];

        foreach ($paths as $path) {
            foreach ($this->buildCandidateUrls($path) as $url) {
                $response = $this->httpClient->request($method, $url, $options);
                $status = $response->getStatusCode();
                $data = $this->decodeJson($response);

                if ($status >= 200 && $status < 300) {
                    return $data;
                }

                if ($status === 404) {
                    $errors[] = sprintf('%s -> 404', $url);
                    continue;
                }

                $detail = (string) ($data['detail'] ?? $data['error'] ?? $response->getContent(false));
                throw new \RuntimeException(sprintf('%s %s failed (%d): %s', $method, $url, $status, $detail));
            }
        }

        throw new \RuntimeException('AI route introuvable. Tried: ' . implode(' | ', $errors));
    }

    /**
     * Check AI service availability.
     * @return array{ollama_available: bool, active_provider: string, available: bool}
     */
    public function getStatus(): array
    {
        return $this->requestWithPathFallback('GET', ['/status'], [
            'timeout' => 10,
        ]);
    }

    /**
     * Generate a quiz via AI.
     * @return array{quiz_id: int, title: string, questions_count: int, difficulty: string, ai_log_id: int, message: string}
     */
    public function generateQuiz(int $userId, int $subjectId, ?int $chapterId, int $numQuestions = 5, string $difficulty = 'MEDIUM', ?string $topic = null): array
    {
        return $this->requestWithPathFallback('POST', ['/generate/quiz', '/geenerate/quiz', '/quiz/generate'], [
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
    }

    /**
     * Generate flashcards via AI.
     * @return array{deck_id: int, title: string, cards_count: int, ai_log_id: int, message: string}
     */
    public function generateFlashcards(int $userId, int $subjectId, ?int $chapterId, int $numCards = 10, ?string $topic = null, bool $includeHints = true): array
    {
        return $this->requestWithPathFallback('POST', ['/generate/flashcards', '/geenerate/flashcards', '/flashcards/generate'], [
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
    }

    /**
     * Enhance user profile with AI suggestions.
     * @return array{suggested_bio: ?string, suggested_goals: ?string, suggested_routine: ?string, ai_log_id: int}
     */
    public function enhanceProfile(int $userId, ?string $currentBio = null, ?string $currentLevel = null, ?string $currentSpecialty = null, ?string $goals = null): array
    {
        return $this->requestWithPathFallback('POST', ['/profile/enhance', '/enhance/profile'], [
            'json' => [
                'user_id' => $userId,
                'current_bio' => $currentBio,
                'current_level' => $currentLevel,
                'current_specialty' => $currentSpecialty,
                'goals' => $goals,
            ],
            'timeout' => 60,
        ]);
    }

    /**
     * Summarize a chapter with AI (summary, key points, tags).
     * @return array{summary: string, key_points: array, tags: array, ai_log_id: int}
     */
    public function summarizeChapter(int $userId, int $chapterId): array
    {
        return $this->requestWithPathFallback('POST', ['/chapter/summarize', '/summarize/chapter'], [
            'json' => [
                'user_id' => $userId,
                'chapter_id' => $chapterId,
            ],
            'timeout' => 60,
        ]);
    }

    /**
     * Get AI suggestions for plan optimization.
     * @return array{suggestions: array, explanation: string, ai_log_id: int, can_apply: bool}
     */
    public function suggestPlanOptimizations(int $userId, int $planId, ?string $optimizationGoals = null): array
    {
        return $this->requestWithPathFallback('POST', ['/planning/suggest', '/suggest/planning', '/ai/planning/suggest'], [
            'json' => [
                'user_id' => $userId,
                'plan_id' => $planId,
                'optimization_goals' => $optimizationGoals,
            ],
            'timeout' => 120,
        ]);
    }

    /**
     * Apply previously generated AI suggestions to a plan.
     * @return array{message: string, applied_count: int, total_suggestions: int}
     */
    public function applyPlanSuggestions(int $userId, int $suggestionLogId): array
    {
        return $this->requestWithPathFallback('POST', ['/planning/apply', '/apply/planning', '/ai/planning/apply'], [
            'json' => [
                'user_id' => $userId,
                'suggestion_log_id' => $suggestionLogId,
            ],
            'timeout' => 30,
        ]);
    }

    /**
     * Summarize a group post with AI.
     * @return array{summary: string, category: string, tags: array, ai_log_id: int}
     */
    public function summarizePost(int $userId, int $postId): array
    {
        return $this->requestWithPathFallback('POST', ['/post/summarize', '/summarize/post'], [
            'json' => [
                'user_id' => $userId,
                'post_id' => $postId,
            ],
            'timeout' => 60,
        ]);
    }

    /**
     * Submit user feedback for an AI generation.
     * @return array{message: string, rating: int}
     */
    public function submitFeedback(int $userId, int $logId, int $rating): array
    {
        return $this->requestWithPathFallback('POST', ['/feedback'], [
            'json' => [
                'user_id' => $userId,
                'log_id' => $logId,
                'rating' => $rating,
            ],
            'timeout' => 10,
        ]);
    }

    /**
     * Get AI usage statistics for BO monitoring.
     * @return array
     */
    public function getStats(): array
    {
        return $this->requestWithPathFallback('GET', ['/logs/stats'], [
            'timeout' => 5,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function translateText(string $text, string $source = 'fr', string $target = 'en'): array
    {
        return $this->requestWithPathFallback('POST', ['/tools/translate'], [
            'json' => [
                'text' => $text,
                'source' => $source,
                'target' => $target,
            ],
            'timeout' => 90,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function defineWord(string $word, string $lang = 'fr'): array
    {
        return $this->requestWithPathFallback('POST', ['/tools/define'], [
            'json' => [
                'word' => $word,
                'lang' => $lang,
            ],
            'timeout' => 90,
        ]);
    }
}
