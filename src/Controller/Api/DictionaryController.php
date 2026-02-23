<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/api/dictionary', name: 'api_dictionary_')]
class DictionaryController extends AbstractController
{
    public function __construct(private readonly HttpClientInterface $httpClient) {}

    #[Route('/{word}', name: 'lookup', methods: ['GET'])]
    public function lookup(string $word): JsonResponse
    {
        $word = trim($word);
        if (strlen($word) < 1 || strlen($word) > 200) {
            return $this->json(['error' => 'Terme invalide.'], 400);
        }

        try {
            $response = $this->httpClient->request('POST', 'http://localhost:8001/api/v1/ai/tools/define', [
                'timeout' => 90,
                'json'    => ['word' => $word, 'lang' => 'fr'],
            ]);

            $result = $response->toArray(false);

            if ($response->getStatusCode() !== 200) {
                return $this->json(['error' => $result['detail'] ?? 'IA indisponible.'], 503);
            }

            return $this->json([
                'word'       => $result['word'] ?? $word,
                'definition' => $result['definition'] ?? '',
                'example'    => $result['example'] ?? '',
            ]);
        } catch (\Throwable) {
            return $this->json(['error' => 'Service IA indisponible.'], 503);
        }
    }
}
