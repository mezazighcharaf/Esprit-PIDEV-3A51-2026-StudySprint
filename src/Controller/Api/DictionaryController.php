<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\AiGatewayService;

#[Route('/api/dictionary', name: 'api_dictionary_')]
class DictionaryController extends AbstractController
{
    public function __construct(private readonly AiGatewayService $aiGateway) {}

    #[Route('/{word}', name: 'lookup', methods: ['GET'])]
    public function lookup(string $word): JsonResponse
    {
        $word = trim($word);
        if (strlen($word) < 1 || strlen($word) > 200) {
            return $this->json(['error' => 'Terme invalide.'], 400);
        }

        try {
            $result = $this->aiGateway->defineWord($word, 'fr');

            return $this->json([
                'word'       => $result['word'] ?? $word,
                'definition' => $result['definition'] ?? '',
                'example'    => $result['example'] ?? '',
            ]);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Service IA indisponible: ' . $e->getMessage()], 503);
        }
    }
}
