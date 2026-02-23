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
    private const OLLAMA_URL   = 'http://localhost:11434/v1/chat/completions';
    private const OLLAMA_MODEL = 'vanilj/qwen2.5-14b-instruct-iq4_xs:latest';
    private const MAX_REQUESTS_PER_HOUR = 20;

    public function __construct(
        private HttpClientInterface $httpClient,
        private EntityManagerInterface $entityManager,
        private ChatbotConfigRepository $configRepository,
        private BotInteractionRepository $interactionRepository,
        private PostCommentRepository $commentRepository,
        private LoggerInterface $logger,
        private string $geminiApiKey = '',
    ) {}

    public function shouldTrigger(string $content, ChatbotConfig $config): bool
    {
        return match ($config->getTriggerMode()) {
            'mention'     => $this->containsKeyword($content, $config->getTriggerKeywords()),
            'auto-detect' => $this->isQuestion($content),
            'keyword'     => $this->containsKeyword($content, $config->getTriggerKeywords()),
            default       => false,
        };
    }

    public function processComment(
        string $commentContent,
        GroupPost $post,
        StudyGroup $group,
        User $triggeredBy,
        ?PostComment $parentForBotReply = null,
    ): ?PostComment {
        $config = $this->configRepository->findEnabledByGroup($group);
        if (!$config) {
            $config = $this->createDefaultConfig($group);
        }

        $isConversationReply = ($parentForBotReply !== null);
        if (!$isConversationReply && !$this->shouldTrigger($commentContent, $config)) {
            return null;
        }

        if ($this->isRateLimited($triggeredBy)) {
            $this->logger->info('Chatbot rate limited for user {userId}', ['userId' => $triggeredBy->getId()]);
            return null;
        }

        try {
            $startTime = microtime(true);

            $prompt = $commentContent;
            if ($isConversationReply) {
                $originalBotComment = $parentForBotReply->getParentComment();
                if ($originalBotComment && $originalBotComment->isBot()) {
                    $prompt = "Contexte de la conversation:\n"
                        . "- Ta réponse précédente: \"" . mb_substr($originalBotComment->getBody(), 0, 500) . "\"\n"
                        . "- Le membre répond: \"" . $commentContent . "\"\n\n"
                        . "Continue la conversation de manière cohérente.";
                }
            }

            $response = $this->generateResponse($prompt, $post, $config);
            $responseTimeMs = (int) ((microtime(true) - $startTime) * 1000);

            $botComment = new PostComment();
            $botComment->setPost($post);
            $botComment->setAuthor($triggeredBy);
            $botComment->setBody($response['text']);
            $botComment->setIsBot(true);
            $botComment->setBotName($config->getBotName());

            if ($parentForBotReply) {
                $botComment->setParentComment($parentForBotReply);
            }

            $this->entityManager->persist($botComment);

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
                'postId'  => $post->getId(),
            ]);
            return null;
        }
    }

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

        if ($this->isRateLimited($author)) {
            return null;
        }

        try {
            $startTime = microtime(true);
            $response = $this->generatePostReaction($post, $config);
            $responseTimeMs = (int) ((microtime(true) - $startTime) * 1000);

            $botComment = new PostComment();
            $botComment->setPost($post);
            $botComment->setAuthor($author);
            $botComment->setBody($response['text']);
            $botComment->setIsBot(true);
            $botComment->setBotName($config->getBotName());

            $this->entityManager->persist($botComment);

            $interaction = new BotInteraction();
            $interaction->setGroup($group);
            $interaction->setPost($post);
            $interaction->setComment($botComment);
            $interaction->setTriggeredBy($author);
            $interaction->setQuestion('[Nouveau post] ' . ($post->getTitle() ?: mb_substr(strip_tags($post->getBody()), 0, 100)));
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
                'postId'  => $post->getId(),
            ]);
            return null;
        }
    }

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

        $botComment = new PostComment();
        $botComment->setPost($post);
        $botComment->setAuthor($user);
        $botComment->setBody($response['text']);
        $botComment->setIsBot(true);
        $botComment->setBotName($config->getBotName());

        $this->entityManager->persist($botComment);

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
            'comment'        => $botComment,
            'interaction'    => $interaction,
            'responseTimeMs' => $responseTimeMs,
        ];
    }

    // ─── Private helpers ────────────────────────────────────────────────────

    private function generateResponse(string $question, GroupPost $post, ChatbotConfig $config): array
    {
        $messages = [
            ['role' => 'system', 'content' => $this->buildSystemPrompt($config) . "\n\n" . $this->buildContextPrompt($post, $config)],
        ];

        // Last 8 comments as conversation context
        $recentComments = $this->commentRepository->findBy(['post' => $post], ['createdAt' => 'ASC']);
        foreach (array_slice($recentComments, -8) as $comment) {
            $authorName = $comment->isBot()
                ? ($comment->getBotName() ?? 'Bot')
                : ($comment->getAuthor()->getPrenom() . ' ' . $comment->getAuthor()->getNom());
            $messages[] = [
                'role'    => $comment->isBot() ? 'assistant' : 'user',
                'content' => "{$authorName}: {$comment->getBody()}",
            ];
        }

        $messages[] = ['role' => 'user', 'content' => $question];

        return $this->callOllama($messages, 600);
    }

    private function generatePostReaction(GroupPost $post, ChatbotConfig $config): array
    {
        $typeLabels = [
            'text'     => 'une publication texte',
            'question' => 'une question',
            'resource' => 'un partage de ressource',
            'file'     => 'un partage de fichier',
            'poll'     => 'un sondage',
        ];
        $typeLabel = $typeLabels[$post->getPostType() ?? 'text'] ?? 'une publication';

        $userPrompt  = "Un membre vient de publier {$typeLabel} dans le groupe \"{$config->getGroup()->getName()}\".";
        if ($post->getTitle()) {
            $userPrompt .= "\nTitre: {$post->getTitle()}";
        }
        $userPrompt .= "\nContenu: {$post->getBody()}";

        $instruction = match ($post->getPostType() ?? 'text') {
            'question' => "Réponds à cette question de manière pédagogique et utile.",
            'resource' => "Commente cette ressource: explique pourquoi c'est utile.",
            'file'     => "Commente ce partage de fichier: explique comment il peut aider l'étude.",
            'poll'     => "Commente ce sondage avec un avis éducatif.",
            default    => "Réagis à cette publication de manière utile et constructive (2-4 phrases max).",
        };

        $userPrompt .= "\n\nInstruction: {$instruction}";
        $userPrompt .= "\nIMPORTANT: Sois bref et naturel (2-4 phrases max). Ne répète pas le contenu du post.";

        $messages = [
            ['role' => 'system', 'content' => $this->buildSystemPrompt($config)],
            ['role' => 'user',   'content' => $userPrompt],
        ];

        return $this->callOllama($messages, 400);
    }

    /**
     * Call Ollama via its OpenAI-compatible endpoint
     */
    private function callOllama(array $messages, int $maxTokens): array
    {
        $response = $this->httpClient->request('POST', self::OLLAMA_URL, [
            'timeout' => 90,
            'json'    => [
                'model'    => self::OLLAMA_MODEL,
                'messages' => $messages,
                'stream'   => false,
                'options'  => [
                    'num_predict' => $maxTokens,
                    'temperature' => 0.7,
                ],
            ],
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode !== 200) {
            $this->logger->error('Ollama chatbot error: {status} {body}', [
                'status' => $statusCode,
                'body'   => $response->getContent(false),
            ]);
            throw new \RuntimeException('L\'IA locale est temporairement indisponible.');
        }

        $data       = $response->toArray();
        $text       = $data['choices'][0]['message']['content'] ?? '';
        $tokensUsed = $data['usage']['total_tokens'] ?? 0;

        if (empty($text)) {
            throw new \RuntimeException('L\'IA n\'a pas pu générer de réponse.');
        }

        // Truncate to configured max length (will be applied by caller context)
        return ['text' => $text, 'tokensUsed' => $tokensUsed];
    }

    private function buildSystemPrompt(ChatbotConfig $config): string
    {
        $personalities = [
            'tutor'       => "Tu es {$config->getBotName()}, un tuteur IA bienveillant et pédagogue dans un groupe d'étude. "
                . "Tu expliques les concepts de manière claire avec des exemples concrets. "
                . "Tu encourages la réflexion plutôt que de donner directement les réponses. "
                . "Tu poses des questions de suivi pour vérifier la compréhension.",
            'assistant'   => "Tu es {$config->getBotName()}, un assistant d'étude efficace. "
                . "Tu fournis des réponses concises et précises. "
                . "Tu structures bien tes réponses avec des listes et des points clés.",
            'mentor'      => "Tu es {$config->getBotName()}, un mentor expérimenté. "
                . "Tu guides les étudiants dans leur raisonnement. "
                . "Tu partages des conseils méthodologiques et des stratégies d'apprentissage. "
                . "Tu motives et encourages les membres du groupe.",
            'quiz-master' => "Tu es {$config->getBotName()}, un maître de quiz interactif. "
                . "Quand on te pose une question, tu réponds ET tu poses une question de suivi pour tester la compréhension. "
                . "Tu donnes des indices progressifs plutôt que des réponses directes.",
        ];

        $prompt = $personalities[$config->getPersonality()] ?? $personalities['tutor'];

        if ($config->getSubjectContext()) {
            $prompt .= "\n\nLe sujet principal du groupe est: {$config->getSubjectContext()}";
        }

        $langLabels = [
            'fr' => 'français', 'en' => 'anglais',   'es' => 'espagnol',
            'de' => 'allemand', 'ar' => 'arabe',      'it' => 'italien',
            'pt' => 'portugais','tr' => 'turc',        'zh' => 'chinois',
        ];
        $lang = $langLabels[$config->getLanguage()] ?? 'français';

        $prompt .= "\n\nRègles importantes:";
        $prompt .= "\n- Réponds en {$lang}";
        $prompt .= "\n- Sois concis (max {$config->getMaxResponseLength()} caractères)";
        $prompt .= "\n- N'utilise PAS de markdown complexe, garde un texte simple et lisible";
        $prompt .= "\n- Tu es dans un contexte éducatif";
        $prompt .= "\n- Ne mentionne jamais que tu es une IA dans tes réponses";

        return $prompt;
    }

    private function buildContextPrompt(GroupPost $post, ChatbotConfig $config): string
    {
        $context  = "Contexte de la discussion:";
        $context .= "\nGroupe: {$config->getGroup()->getName()}";
        if ($post->getTitle()) {
            $context .= "\nTitre du post: {$post->getTitle()}";
        }
        $context .= "\nContenu du post: {$post->getBody()}";
        return $context;
    }

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

    private function isQuestion(string $content): bool
    {
        $trimmed = trim($content);

        if (str_ends_with($trimmed, '?')) {
            return true;
        }

        $patterns = [
            '/^(comment|pourquoi|quand|où|qui|quel|quelle|quels|quelles|combien|est-ce que|qu\'est)/iu',
            '/(expliquer|explique|aide|aidez|comprends pas|je ne sais pas|c\'est quoi)/iu',
            '/^(how|why|when|where|who|what|which|can|could|would|is|are|do|does)/iu',
            '/(explain|help|understand|don\'t know)/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $trimmed)) {
                return true;
            }
        }

        return false;
    }

    private function isRateLimited(User $user): bool
    {
        $recentCount = $this->interactionRepository->countRecentByUser($user->getId(), 60);
        return $recentCount >= self::MAX_REQUESTS_PER_HOUR;
    }

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
