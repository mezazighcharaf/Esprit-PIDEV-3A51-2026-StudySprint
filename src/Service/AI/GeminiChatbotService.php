<?php

namespace App\Service\AI;

use App\Entity\BotInteraction;
use App\Entity\ChatbotConfig;
use App\Entity\GroupPost;
use App\Entity\PostComment;
use App\Entity\StudyGroup;
use App\Entity\User;
use App\Repository\BotInteractionRepository;
use App\Repository\ChatbotConfigRepository;
use App\Repository\PostCommentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GeminiChatbotService
{
    private const GEMINI_API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';
    private const MAX_REQUESTS_PER_HOUR = 20;

    public function __construct(
        private HttpClientInterface $httpClient,
        private EntityManagerInterface $entityManager,
        private ChatbotConfigRepository $configRepository,
        private BotInteractionRepository $interactionRepository,
        private PostCommentRepository $commentRepository,
        private LoggerInterface $logger,
        private string $geminiApiKey,
    ) {}

    /**
     * Check if a comment should trigger the bot
     */
    public function shouldTrigger(string $content, ChatbotConfig $config): bool
    {
        return match ($config->getTriggerMode()) {
            'mention' => $this->containsKeyword($content, $config->getTriggerKeywords()),
            'auto-detect' => $this->isQuestion($content),
            'keyword' => $this->containsKeyword($content, $config->getTriggerKeywords()),
            default => false,
        };
    }

    /**
     * Process a comment and generate a bot reply if needed
     */
    public function processComment(
        string $commentContent,
        GroupPost $post,
        StudyGroup $group,
        User $triggeredBy,
        ?PostComment $parentForBotReply = null,
    ): ?PostComment {
        $config = $this->configRepository->findEnabledByGroup($group);
        if (!$config) {
            // Auto-create a default config for this group
            $config = $this->createDefaultConfig($group);
        }

        // If this is a reply to a bot comment, always trigger (conversation mode)
        $isConversationReply = ($parentForBotReply !== null);
        if (!$isConversationReply && !$this->shouldTrigger($commentContent, $config)) {
            return null;
        }

        // Rate limiting
        if ($this->isRateLimited($triggeredBy)) {
            $this->logger->info('Chatbot rate limited for user {userId}', [
                'userId' => $triggeredBy->getId(),
            ]);
            return null;
        }

        try {
            $startTime = microtime(true);

            // Build conversation context if replying to bot
            $prompt = $commentContent;
            if ($isConversationReply) {
                // Get the original bot message being replied to (grandparent)
                $originalBotComment = $parentForBotReply->getParentComment();
                if ($originalBotComment && $originalBotComment->isBot()) {
                    $prompt = "Contexte de la conversation:\n"
                        . "- Ta réponse précédente: \"" . mb_substr($originalBotComment->getBody() ?? '', 0, 500) . "\"\n"
                        . "- Le membre répond: \"" . $commentContent . "\"\n\n"
                        . "Continue la conversation de manière cohérente en répondant à ce que le membre dit.";
                }
            }

            $response = $this->generateResponse($prompt, $post, $config);

            $responseTimeMs = (int) ((microtime(true) - $startTime) * 1000);

            // Create bot comment
            $botComment = new PostComment();
            $botComment->setPost($post);
            $botComment->setAuthor($triggeredBy); // Will be marked as bot
            $botComment->setBody($response['text']);
            $botComment->setIsBot(true);
            $botComment->setBotName($config->getBotName());

            // Thread the bot reply under the member's comment
            if ($parentForBotReply) {
                $botComment->setParentComment($parentForBotReply);
            }

            $this->entityManager->persist($botComment);

            // Log interaction
            $interaction = new BotInteraction();
            $interaction->setGroup($group);
            $interaction->setPost($post);
            $interaction->setComment($botComment);
            $interaction->setTriggeredBy($triggeredBy);
            $interaction->setQuestion($commentContent);
            $interaction->setResponse($response['text']);
            $interaction->setTokensUsed($response['tokensUsed']);
            $interaction->setResponseTimeMs($responseTimeMs);

            $this->entityManager->persist($interaction);
            $this->entityManager->flush();

            return $botComment;
        } catch (\Exception $e) {
            $this->logger->error('Chatbot error: {message}', [
                'message' => $e->getMessage(),
                'groupId' => $group->getId(),
                'postId' => $post->getId(),
            ]);
            return null;
        }
    }

    /**
     * Automatically generate a bot comment on a new post
     */
    public function processNewPost(
        GroupPost $post,
        StudyGroup $group,
        User $author,
    ): ?PostComment {
        $config = $this->configRepository->findEnabledByGroup($group);
        if (!$config) {
            $config = $this->createDefaultConfig($group);
        }

        if (!$config->isEnabled()) {
            return null;
        }

        // Rate limiting
        if ($this->isRateLimited($author)) {
            return null;
        }

        try {
            $startTime = microtime(true);

            $response = $this->generatePostReaction($post, $config);

            $responseTimeMs = (int) ((microtime(true) - $startTime) * 1000);

            // Create bot comment
            $botComment = new PostComment();
            $botComment->setPost($post);
            $botComment->setAuthor($author);
            $botComment->setBody($response['text']);
            $botComment->setIsBot(true);
            $botComment->setBotName($config->getBotName());

            $this->entityManager->persist($botComment);

            // Log interaction
            $interaction = new BotInteraction();
            $interaction->setGroup($group);
            $interaction->setPost($post);
            $interaction->setComment($botComment);
            $interaction->setTriggeredBy($author);
            $interaction->setQuestion('[Nouveau post] ' . ($post->getTitle() ?: mb_substr(strip_tags($post->getBody() ?? ''), 0, 100)));
            $interaction->setResponse($response['text']);
            $interaction->setTokensUsed($response['tokensUsed']);
            $interaction->setResponseTimeMs($responseTimeMs);

            $this->entityManager->persist($interaction);
            $this->entityManager->flush();

            return $botComment;
        } catch (\Exception $e) {
            $this->logger->error('Chatbot new post error: {message}', [
                'message' => $e->getMessage(),
                'groupId' => $group->getId(),
                'postId' => $post->getId(),
            ]);
            return null;
        }
    }

    /**
     * Ask the bot a direct question (API endpoint)
     * 
     * @return array{comment: PostComment, interaction: BotInteraction, responseTimeMs: int}
     */
    public function askQuestion(
        string $question,
        GroupPost $post,
        StudyGroup $group,
        User $user,
    ): array {
        $config = $this->configRepository->findEnabledByGroup($group);
        if (!$config) {
            throw new \RuntimeException('Le chatbot n\'est pas activé pour ce groupe.');
        }

        if ($this->isRateLimited($user)) {
            throw new \RuntimeException('Vous avez atteint la limite de questions par heure. Réessayez plus tard.');
        }

        $startTime = microtime(true);
        $response = $this->generateResponse($question, $post, $config);
        $responseTimeMs = (int) ((microtime(true) - $startTime) * 1000);

        // Create bot comment
        $botComment = new PostComment();
        $botComment->setPost($post);
        $botComment->setAuthor($user);
        $botComment->setBody($response['text']);
        $botComment->setIsBot(true);
        $botComment->setBotName($config->getBotName());

        $this->entityManager->persist($botComment);

        // Log interaction
        $interaction = new BotInteraction();
        $interaction->setGroup($group);
        $interaction->setPost($post);
        $interaction->setComment($botComment);
        $interaction->setTriggeredBy($user);
        $interaction->setQuestion($question);
        $interaction->setResponse($response['text']);
        $interaction->setTokensUsed($response['tokensUsed']);
        $interaction->setResponseTimeMs($responseTimeMs);

        $this->entityManager->persist($interaction);
        $this->entityManager->flush();

        return [
            'comment' => $botComment,
            'interaction' => $interaction,
            'responseTimeMs' => $responseTimeMs,
        ];
    }

    /**
     * Generate a response using Google Gemini API
     * 
     * @return array{text: string, tokensUsed: int}
     */
    private function generateResponse(string $question, GroupPost $post, ChatbotConfig $config): array
    {
        $systemPrompt = $this->buildSystemPrompt($config);
        $contextPrompt = $this->buildContextPrompt($post, $config);

        // Get recent comments for conversation context
        $recentComments = $this->commentRepository->findBy(
            ['post' => $post],
            ['createdAt' => 'ASC'],
        );

        // Build conversation parts
        $parts = [];
        $parts[] = ['text' => $systemPrompt . "\n\n" . $contextPrompt];

        // Add recent comments as context (last 8)
        $recentSlice = array_slice($recentComments, -8);
        foreach ($recentSlice as $comment) {
            $authorName = $comment->isBot()
                ? $comment->getBotName() ?? 'Bot'
                : ($comment->getAuthor() ? ($comment->getAuthor()->getPrenom() . ' ' . $comment->getAuthor()->getNom()) : 'Utilisateur');
            $parts[] = ['text' => "{$authorName}: {$comment->getBody()}"];
        }

        $parts[] = ['text' => "Question de l'utilisateur: {$question}"];

        $payload = [
            'contents' => [
                [
                    'parts' => $parts,
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 600,
                'topP' => 0.95,
                'topK' => 40,
            ],
            'safetySettings' => [
                ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_ONLY_HIGH'],
                ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_ONLY_HIGH'],
                ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_ONLY_HIGH'],
                ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_ONLY_HIGH'],
            ],
        ];

        $response = $this->httpClient->request('POST', self::GEMINI_API_URL, [
            'query' => ['key' => $this->geminiApiKey],
            'json' => $payload,
            'timeout' => 30,
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode !== 200) {
            $this->logger->error('Gemini API error: {status} {body}', [
                'status' => $statusCode,
                'body' => $response->getContent(false),
            ]);
            throw new \RuntimeException('L\'IA est temporairement indisponible. Réessayez dans quelques instants.');
        }

        $data = $response->toArray();

        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $tokensUsed = ($data['usageMetadata']['totalTokenCount'] ?? 0);

        if (empty($text)) {
            throw new \RuntimeException('L\'IA n\'a pas pu générer de réponse.');
        }

        // Truncate if too long
        if (mb_strlen($text) > $config->getMaxResponseLength()) {
            $text = mb_substr($text, 0, $config->getMaxResponseLength()) . '...';
        }

        return [
            'text' => $text,
            'tokensUsed' => $tokensUsed,
        ];
    }

    /**
     * Build the system prompt based on the bot personality
     */
    private function buildSystemPrompt(ChatbotConfig $config): string
    {
        $personalities = [
            'tutor' => "Tu es {$config->getBotName()}, un tuteur IA bienveillant et pédagogue dans un groupe d'étude. "
                . "Tu expliques les concepts de manière claire avec des exemples concrets. "
                . "Tu encourages la réflexion plutôt que de donner directement les réponses. "
                . "Tu poses des questions de suivi pour vérifier la compréhension.",
            'assistant' => "Tu es {$config->getBotName()}, un assistant d'étude efficace. "
                . "Tu fournis des réponses concises et précises. "
                . "Tu structures bien tes réponses avec des listes et des points clés.",
            'mentor' => "Tu es {$config->getBotName()}, un mentor expérimenté. "
                . "Tu guides les étudiants dans leur raisonnement. "
                . "Tu partages des conseils méthodologiques et des stratégies d'apprentissage. "
                . "Tu motives et encourages les membres du groupe.",
            'quiz-master' => "Tu es {$config->getBotName()}, un maître de quiz interactif. "
                . "Quand on te pose une question, tu réponds ET tu poses une question de suivi pour tester la compréhension. "
                . "Tu crées des mini-quiz quand on te le demande. "
                . "Tu donnes des indices progressifs plutôt que des réponses directes.",
        ];

        $prompt = $personalities[$config->getPersonality()] ?? $personalities['tutor'];

        if ($config->getSubjectContext()) {
            $prompt .= "\n\nLe sujet principal du groupe est: {$config->getSubjectContext()}";
        }

        $lang = $config->getLanguage() === 'fr' ? 'français' : 'anglais';
        $prompt .= "\n\nRègles importantes:";
        $prompt .= "\n- Réponds en {$lang}";
        $prompt .= "\n- Sois concis (max {$config->getMaxResponseLength()} caractères)";
        $prompt .= "\n- N'utilise PAS de markdown complexe, garde un texte simple et lisible";
        $prompt .= "\n- Tu es dans un contexte éducatif universitaire";
        $prompt .= "\n- Ne mentionne jamais que tu es une IA ou un chatbot dans tes réponses";

        return $prompt;
    }

    /**
     * Build context from the post
     */
    private function buildContextPrompt(GroupPost $post, ChatbotConfig $config): string
    {
        $context = "Contexte de la discussion:";
        $group = $config->getGroup();
        if ($group) {
            $context .= "\nGroupe: {$group->getName()}";
        }

        if ($post->getTitle()) {
            $context .= "\nTitre du post: {$post->getTitle()}";
        }

        $context .= "\nContenu du post: {$post->getBody()}";

        return $context;
    }

    /**
     * @param array<int, string> $keywords
     */
    private function containsKeyword(string $content, array $keywords): bool
    {
        $contentLower = mb_strtolower($content);
        foreach ($keywords as $keyword) {
            if (str_contains($contentLower, mb_strtolower($keyword))) {
                return true;
            }
        }
        return false;
    }

    /**
     * Detect if content is a question
     */
    private function isQuestion(string $content): bool
    {
        $trimmed = trim($content);

        // Ends with ?
        if (str_ends_with($trimmed, '?')) {
            return true;
        }

        // French question words
        $frenchPatterns = [
            '/^(comment|pourquoi|quand|où|qui|quel|quelle|quels|quelles|combien|est-ce que|qu\'est)/iu',
            '/(expliquer|explique|aide|aidez|comprends pas|je ne sais pas|c\'est quoi)/iu',
        ];

        // English question words
        $englishPatterns = [
            '/^(how|why|when|where|who|what|which|can|could|would|is|are|do|does)/iu',
            '/(explain|help|understand|don\'t know)/iu',
        ];

        foreach (array_merge($frenchPatterns, $englishPatterns) as $pattern) {
            if (preg_match($pattern, $trimmed)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user is rate limited
     */
    private function isRateLimited(User $user): bool
    {
        $recentCount = $this->interactionRepository->countRecentByUser(
            (int) $user->getId(),
            60
        );

        return $recentCount >= self::MAX_REQUESTS_PER_HOUR;
    }

    /**
     * Generate a reaction/contribution to a new post
     * 
     * @return array{text: string, tokensUsed: int}
     */
    private function generatePostReaction(GroupPost $post, ChatbotConfig $config): array
    {
        $systemPrompt = $this->buildSystemPrompt($config);

        $postType = $post->getPostType() ?? 'text';
        $typeLabels = [
            'text' => 'une publication texte',
            'question' => 'une question',
            'resource' => 'un partage de ressource',
            'file' => 'un partage de fichier',
            'poll' => 'un sondage',
        ];
        $typeLabel = $typeLabels[$postType] ?? 'une publication';

        $postPrompt = "Un membre vient de publier {$typeLabel} dans le groupe.";
        $group = $config->getGroup();
        if ($group) {
            $postPrompt .= "\nGroupe: {$group->getName()}";
        }

        if ($post->getTitle()) {
            $postPrompt .= "\nTitre: {$post->getTitle()}";
        }

        $postPrompt .= "\nContenu: {$post->getBody()}";

        $instruction = match ($postType) {
            'question' => "Réponds à cette question de manière pédagogique et utile.",
            'resource' => "Commente cette ressource: explique pourquoi c'est utile, ajoute des conseils d'utilisation ou des compléments.",
            'file' => "Commente ce partage de fichier: explique comment il peut être utile pour l'étude.",
            'poll' => "Commente ce sondage: donne ton avis éducatif et explique les différentes options.",
            default => "Réagis à cette publication de manière utile et constructive. Tu peux: poser une question de réflexion, ajouter un complément d'information, encourager la discussion, ou proposer un point de vue complémentaire.",
        };

        $postPrompt .= "\n\nInstruction: {$instruction}";
        $postPrompt .= "\nIMPORTANT: Sois bref et naturel (2-4 phrases max). Ne répète pas le contenu du post.";

        $parts = [
            ['text' => $systemPrompt . "\n\n" . $postPrompt],
        ];

        $payload = [
            'contents' => [['parts' => $parts]],
            'generationConfig' => [
                'temperature' => 0.8,
                'maxOutputTokens' => 400,
                'topP' => 0.95,
                'topK' => 40,
            ],
            'safetySettings' => [
                ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_ONLY_HIGH'],
                ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_ONLY_HIGH'],
                ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_ONLY_HIGH'],
                ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_ONLY_HIGH'],
            ],
        ];

        $response = $this->httpClient->request('POST', self::GEMINI_API_URL, [
            'query' => ['key' => $this->geminiApiKey],
            'json' => $payload,
            'timeout' => 30,
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode !== 200) {
            $this->logger->error('Gemini API error on post reaction: {status}', [
                'status' => $statusCode,
            ]);
            throw new \RuntimeException('IA indisponible');
        }

        $data = $response->toArray();
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $tokensUsed = ($data['usageMetadata']['totalTokenCount'] ?? 0);

        if (empty($text)) {
            throw new \RuntimeException('Pas de réponse IA');
        }

        if (mb_strlen($text) > $config->getMaxResponseLength()) {
            $text = mb_substr($text, 0, $config->getMaxResponseLength()) . '...';
        }

        return [
            'text' => $text,
            'tokensUsed' => $tokensUsed,
        ];
    }

    /**
     * Auto-create a default chatbot config for a group
     */
    private function createDefaultConfig(StudyGroup $group): ChatbotConfig
    {
        $config = new ChatbotConfig();
        $config->setGroup($group);
        $config->setIsEnabled(true);
        $config->setBotName('StudyBot');
        $config->setPersonality('tutor');
        $config->setTriggerMode('auto-detect');
        $config->setLanguage('fr');
        $config->setMaxResponseLength(800);
        $config->setSubjectContext($group->getSubject());

        $this->entityManager->persist($config);
        $this->entityManager->flush();

        return $config;
    }
}
