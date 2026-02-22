<?php

namespace App\Controller\Api;

use App\Service\DictionaryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/dictionary', name: 'api_dictionary_')]
class DictionaryController extends AbstractController
{
    #[Route('/{word}', name: 'lookup', methods: ['GET'])]
    public function lookup(string $word, DictionaryService $dictionaryService): JsonResponse
    {
        $word = trim($word);
        if (strlen($word) < 1 || strlen($word) > 100) {
            return $this->json(['error' => 'Mot invalide.'], 400);
        }

        $result = $dictionaryService->lookup($word);

        if ($result === null) {
            return $this->json(['error' => 'Définition introuvable pour ce mot.'], 404);
        }

        return $this->json($result);
    }
}
