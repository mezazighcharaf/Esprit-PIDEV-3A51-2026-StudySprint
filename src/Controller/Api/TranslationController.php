<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\AiGatewayService;

#[Route('/api', name: 'api_translation_')]
class TranslationController extends AbstractController
{
    public function __construct(private readonly AiGatewayService $aiGateway) {}

    #[Route('/translate', name: 'translate', methods: ['POST'])]
    public function translate(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'JSON invalide.'], 400);
        }

        $text   = trim($data['text'] ?? '');
        $source = $data['source'] ?? 'fr';
        $target = $data['target'] ?? 'en';

        if ($text === '') {
            return $this->json(['error' => 'Le texte est vide.'], 400);
        }

        if (strlen($text) > 500) {
            return $this->json(['error' => 'Texte trop long (max 500 caractères).'], 400);
        }

        try {
            $result = $this->aiGateway->translateText($text, $source, $target);

            return $this->json([
                'original'   => $text,
                'translated' => $result['translated'] ?? '',
                'source'     => $source,
                'target'     => $target,
            ]);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Service IA indisponible: ' . $e->getMessage()], 503);
        }
    }
}
