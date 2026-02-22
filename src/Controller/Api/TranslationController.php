<?php

namespace App\Controller\Api;

use App\Service\TranslationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api', name: 'api_translation_')]
class TranslationController extends AbstractController
{
    #[Route('/translate', name: 'translate', methods: ['POST'])]
    public function translate(Request $request, TranslationService $translationService): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'JSON invalide.'], 400);
        }

        $text       = trim($data['text'] ?? '');
        $sourceLang = $data['source'] ?? 'auto';
        $targetLang = $data['target'] ?? 'en';

        if ($text === '') {
            return $this->json(['error' => 'Le texte est vide.'], 400);
        }

        if (strlen($text) > 2000) {
            return $this->json(['error' => 'Texte trop long (max 2000 caractères).'], 400);
        }

        $translated = $translationService->translate($text, $sourceLang, $targetLang);

        if ($translated === null) {
            return $this->json(['error' => 'Traduction impossible. Le service est peut-être indisponible.'], 503);
        }

        return $this->json([
            'original'   => $text,
            'translated' => $translated,
            'source'     => $sourceLang,
            'target'     => $targetLang,
        ]);
    }
}
