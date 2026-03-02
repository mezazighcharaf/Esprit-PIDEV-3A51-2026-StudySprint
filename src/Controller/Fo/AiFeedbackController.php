<?php

namespace App\Controller\Fo;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\AiGatewayService;

#[Route('/fo/ai', name: 'fo_ai_')]
/**
 * @method \App\Entity\User|null getUser()
 */
class AiFeedbackController extends AbstractController
{
    #[Route('/feedback', name: 'feedback', methods: ['POST'])]
    public function feedback(
        Request $request,
        AiGatewayService $aiGateway
    ): JsonResponse {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Non authentifié'], 401);
        }

        $data = json_decode($request->getContent(), true);
        $logId = $data['log_id'] ?? null;
        $rating = $data['rating'] ?? null;

        if (!$logId || !$rating || $rating < 1 || $rating > 5) {
            return new JsonResponse(['error' => 'Paramètres invalides (log_id et rating 1-5 requis)'], 400);
        }

        try {
            $aiGateway->submitFeedback($user->getId(), (int) $logId, (int) $rating);
            return new JsonResponse(['status' => 'success', 'message' => 'Merci pour votre retour !']);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Service indisponible'], 503);
        }
    }
}
