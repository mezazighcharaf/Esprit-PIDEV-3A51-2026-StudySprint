<?php

namespace App\Controller\Api;

use App\Entity\BotInteraction;
use App\Entity\ChatbotConfig;
use App\Entity\User;
use App\Repository\BotInteractionRepository;
use App\Repository\ChatbotConfigRepository;
use App\Repository\GroupMemberRepository;
use App\Repository\GroupPostRepository;
use App\Repository\StudyGroupRepository;
use App\Service\AI\GeminiChatbotService;
use App\Service\FormattingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/app/api/chatbot')]
class ChatbotController extends AbstractController
{
    public function __construct(
        private GeminiChatbotService $chatbotService,
        private ChatbotConfigRepository $configRepository,
        private BotInteractionRepository $interactionRepository,
        private StudyGroupRepository $groupRepository,
        private GroupPostRepository $postRepository,
        private GroupMemberRepository $memberRepository,
        private EntityManagerInterface $entityManager,
        private FormattingService $formattingService,
    ) {}

    // ==================== CONFIGURATION ====================

    /**
     * Get chatbot config for a group
     */
    #[Route('/groups/{groupId}/config', name: 'api_chatbot_config', methods: ['GET'])]
    public function getConfig(int $groupId): JsonResponse
    {
        $group = $this->groupRepository->find($groupId);
        if (!$group) {
            return $this->json(['success' => false, 'error' => 'Groupe non trouvé'], 404);
        }

        $config = $this->configRepository->findByGroup($group);

        if (!$config) {
            return $this->json([
                'success' => true,
                'isConfigured' => false,
            ]);
        }

        return $this->json([
            'success' => true,
            'isConfigured' => true,
            'config' => $this->formatConfig($config),
        ]);
    }

    /**
     * Save/update chatbot config
     */
    #[Route('/groups/{groupId}/config', name: 'api_chatbot_config_save', methods: ['POST'])]
    public function saveConfig(int $groupId, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['success' => false, 'error' => 'Non authentifié'], 401);
        }

        $group = $this->groupRepository->find($groupId);
        if (!$group) {
            return $this->json(['success' => false, 'error' => 'Groupe non trouvé'], 404);
        }

        // Check admin permission
        $membership = $this->memberRepository->findOneBy(['group' => $group, 'user' => $user]);
        if (!$membership || $membership->getMemberRole() !== 'admin') {
            return $this->json(['success' => false, 'error' => 'Seul l\'admin peut configurer le chatbot'], 403);
        }

        $data = json_decode($request->getContent(), true);
        if (!$data) {
            $data = $request->request->all();
        }

        $config = $this->configRepository->findByGroup($group);
        if (!$config) {
            $config = new ChatbotConfig();
            $config->setGroup($group);
        }

        // Update fields
        if (isset($data['botName'])) {
            $config->setBotName(trim($data['botName']) ?: 'StudyBot');
        }
        if (isset($data['personality'])) {
            $validPersonalities = ['tutor', 'assistant', 'mentor', 'quiz-master'];
            if (in_array($data['personality'], $validPersonalities, true)) {
                $config->setPersonality($data['personality']);
            }
        }
        if (isset($data['subjectContext'])) {
            $config->setSubjectContext(trim($data['subjectContext']) ?: null);
        }
        if (isset($data['triggerMode'])) {
            $validModes = ['mention', 'auto-detect', 'keyword'];
            if (in_array($data['triggerMode'], $validModes, true)) {
                $config->setTriggerMode($data['triggerMode']);
            }
        }
        if (isset($data['triggerKeywords']) && is_array($data['triggerKeywords'])) {
            $config->setTriggerKeywords(array_filter(array_map('trim', $data['triggerKeywords'])));
        }
        if (isset($data['maxResponseLength'])) {
            $len = max(100, min(2000, (int) $data['maxResponseLength']));
            $config->setMaxResponseLength($len);
        }
        if (isset($data['language'])) {
            $validLanguages = ['fr', 'en', 'es', 'de', 'ar', 'it', 'pt', 'tr', 'zh'];
            $config->setLanguage(in_array($data['language'], $validLanguages, true) ? $data['language'] : 'fr');
        }
        if (isset($data['isEnabled'])) {
            $config->setIsEnabled((bool) $data['isEnabled']);
        }

        $config->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($config);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Configuration du chatbot sauvegardée',
            'config' => $this->formatConfig($config),
        ]);
    }

    /**
     * Toggle chatbot on/off
     */
    #[Route('/groups/{groupId}/toggle', name: 'api_chatbot_toggle', methods: ['POST'])]
    public function toggleBot(int $groupId, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['success' => false, 'error' => 'Non authentifié'], 401);
        }

        $group = $this->groupRepository->find($groupId);
        if (!$group) {
            return $this->json(['success' => false, 'error' => 'Groupe non trouvé'], 404);
        }

        $membership = $this->memberRepository->findOneBy(['group' => $group, 'user' => $user]);
        if (!$membership || $membership->getMemberRole() !== 'admin') {
            return $this->json(['success' => false, 'error' => 'Permission refusée'], 403);
        }

        $config = $this->configRepository->findByGroup($group);
        if (!$config) {
            return $this->json(['success' => false, 'error' => 'Chatbot non configuré'], 404);
        }

        $config->setIsEnabled(!$config->isEnabled());
        $config->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'isEnabled' => $config->isEnabled(),
            'message' => $config->isEnabled() ? 'Chatbot activé' : 'Chatbot désactivé',
        ]);
    }

    // ==================== ASK QUESTION ====================

    /**
     * Ask the chatbot a direct question
     */
    #[Route('/groups/{groupId}/posts/{postId}/ask', name: 'api_chatbot_ask', methods: ['POST'])]
    public function askQuestion(int $groupId, int $postId, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['success' => false, 'error' => 'Non authentifié'], 401);
        }

        $group = $this->groupRepository->find($groupId);
        if (!$group) {
            return $this->json(['success' => false, 'error' => 'Groupe non trouvé'], 404);
        }

        // Check membership
        $membership = $this->memberRepository->findOneBy(['group' => $group, 'user' => $user]);
        if (!$membership) {
            return $this->json(['success' => false, 'error' => 'Vous devez être membre du groupe'], 403);
        }

        $post = $this->postRepository->find($postId);
        if (!$post || $post->getGroup()->getId() !== $group->getId()) {
            return $this->json(['success' => false, 'error' => 'Post non trouvé'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $question = trim($data['question'] ?? '');

        if (empty($question)) {
            return $this->json(['success' => false, 'error' => 'La question ne peut pas être vide'], 400);
        }

        if (mb_strlen($question) > 1000) {
            return $this->json(['success' => false, 'error' => 'La question est trop longue (max 1000 caractères)'], 400);
        }

        try {
            $result = $this->chatbotService->askQuestion($question, $post, $group, $user);
            $botComment = $result['comment'];

            return $this->json([
                'success' => true,
                'comment' => [
                    'id' => $botComment->getId(),
                    'body' => $botComment->getBody(),
                    'isBot' => true,
                    'botName' => $botComment->getBotName(),
                    'author' => $this->formattingService->formatUserForView($user),
                    'createdAt' => $botComment->getCreatedAt()->format('c'),
                    'timeAgo' => $this->formattingService->formatTimeAgo($botComment->getCreatedAt()),
                    'interactionId' => $result['interaction']->getId(),
                    'responseTimeMs' => $result['responseTimeMs'],
                ],
            ]);
        } catch (\RuntimeException $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_TOO_MANY_REQUESTS);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Erreur lors de la génération de la réponse. Réessayez.',
            ], 500);
        }
    }

    // ==================== FEEDBACK ====================

    /**
     * Submit feedback on a bot interaction
     */
    #[Route('/interactions/{interactionId}/feedback', name: 'api_chatbot_feedback', methods: ['POST'])]
    public function submitFeedback(int $interactionId, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['success' => false, 'error' => 'Non authentifié'], 401);
        }

        $interaction = $this->interactionRepository->find($interactionId);
        if (!$interaction) {
            return $this->json(['success' => false, 'error' => 'Interaction non trouvée'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $feedback = $data['feedback'] ?? '';

        if (!in_array($feedback, ['helpful', 'not-helpful'], true)) {
            return $this->json(['success' => false, 'error' => 'Feedback invalide'], 400);
        }

        $interaction->setFeedback($feedback);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Merci pour votre feedback !',
        ]);
    }

    // ==================== STATS ====================

    /**
     * Get chatbot stats for a group
     */
    #[Route('/groups/{groupId}/stats', name: 'api_chatbot_stats', methods: ['GET'])]
    public function getStats(int $groupId): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['success' => false, 'error' => 'Non authentifié'], 401);
        }

        $group = $this->groupRepository->find($groupId);
        if (!$group) {
            return $this->json(['success' => false, 'error' => 'Groupe non trouvé'], 404);
        }

        $membership = $this->memberRepository->findOneBy(['group' => $group, 'user' => $user]);
        if (!$membership || $membership->getMemberRole() !== 'admin') {
            return $this->json(['success' => false, 'error' => 'Permission refusée'], 403);
        }

        try {
            $stats = $this->interactionRepository->getGroupStats($group);
            $recentInteractions = $this->interactionRepository->findRecentByGroup($group, 10);

            $recent = array_map(function (BotInteraction $i) {
                return [
                    'id' => $i->getId(),
                    'question' => $i->getQuestion(),
                    'response' => mb_substr($i->getResponse(), 0, 150) . (mb_strlen($i->getResponse()) > 150 ? '...' : ''),
                    'feedback' => $i->getFeedback(),
                    'tokensUsed' => $i->getTokensUsed(),
                    'responseTimeMs' => $i->getResponseTimeMs(),
                    'createdAt' => $i->getCreatedAt()->format('c'),
                ];
            }, $recentInteractions);

            return $this->json([
                'success' => true,
                'stats' => [
                    'totalInteractions' => (int) ($stats['totalInteractions'] ?? 0),
                    'avgResponseTime' => round((float) ($stats['avgResponseTime'] ?? 0)),
                    'totalTokens' => (int) ($stats['totalTokens'] ?? 0),
                    'helpfulCount' => (int) ($stats['helpfulCount'] ?? 0),
                    'notHelpfulCount' => (int) ($stats['notHelpfulCount'] ?? 0),
                ],
                'recentInteractions' => $recent,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => true,
                'stats' => [
                    'totalInteractions' => 0,
                    'avgResponseTime' => 0,
                    'totalTokens' => 0,
                    'helpfulCount' => 0,
                    'notHelpfulCount' => 0,
                ],
                'recentInteractions' => [],
            ]);
        }
    }

    // ==================== HELPERS ====================

    private function formatConfig(ChatbotConfig $config): array
    {
        return [
            'id' => $config->getId(),
            'isEnabled' => $config->isEnabled(),
            'botName' => $config->getBotName(),
            'personality' => $config->getPersonality(),
            'subjectContext' => $config->getSubjectContext(),
            'triggerMode' => $config->getTriggerMode(),
            'triggerKeywords' => $config->getTriggerKeywords(),
            'maxResponseLength' => $config->getMaxResponseLength(),
            'language' => $config->getLanguage(),
        ];
    }
}
