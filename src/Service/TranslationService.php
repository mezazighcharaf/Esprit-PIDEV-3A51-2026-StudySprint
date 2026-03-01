<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TranslationService
{
    // Public Lingva Translate instances (fallback if one is down)
    private const LINGVA_INSTANCES = [
        'https://lingva.ml',
        'https://lingva.thedaviddelta.com',
        'https://translate.plausibility.cloud',
    ];

    private const SUPPORTED_LANGUAGES = [
        'fr' => 'Français',
        'en' => 'English',
        'es' => 'Español',
        'de' => 'Deutsch',
        'ar' => 'العربية',
        'zh' => '中文',
        'it' => 'Italiano',
        'pt' => 'Português',
        'ru' => 'Русский',
        'ja' => '日本語',
        'ko' => '한국어',
        'tr' => 'Türkçe',
        'nl' => 'Nederlands',
    ];

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
    ) {}

    /**
     * Translate text from source language to target language.
     * Uses Lingva Translate API (free, no API key required).
     */
    public function translate(string $text, string $targetLang, string $sourceLang = 'auto'): ?string
    {
        if (empty(trim($text))) {
            return null;
        }

        // Truncate if too long (reasonable limit per request)
        $text = mb_substr($text, 0, 5000);

        foreach (self::LINGVA_INSTANCES as $instance) {
            try {
                $this->logger->info('[TranslationService] Traduction via ' . $instance, [
                    'source' => $sourceLang,
                    'target' => $targetLang,
                    'textLength' => mb_strlen($text),
                ]);

                $response = $this->httpClient->request('GET', sprintf(
                    '%s/api/v1/%s/%s/%s',
                    $instance,
                    $sourceLang,
                    $targetLang,
                    rawurlencode($text)
                ), [
                    'timeout' => 10,
                ]);

                $data = $response->toArray();

                if (!empty($data['translation'])) {
                    $this->logger->info('[TranslationService] Traduction réussie');
                    return $data['translation'];
                }

            } catch (\Exception $e) {
                $this->logger->warning('[TranslationService] Échec sur ' . $instance . ' : ' . $e->getMessage());
                continue; // Try next instance
            }
        }

        $this->logger->error('[TranslationService] Toutes les instances ont échoué');
        return null;
    }

    /**
     * Detect the language of a text.
     */
    public function detectLanguage(string $text): ?string
    {
        foreach (self::LINGVA_INSTANCES as $instance) {
            try {
                $response = $this->httpClient->request('GET', sprintf(
                    '%s/api/v1/auto/en/%s',
                    $instance,
                    rawurlencode(mb_substr($text, 0, 200))
                ), [
                    'timeout' => 5,
                ]);

                $data = $response->toArray();

                if (!empty($data['info']['detectedSource'])) {
                    return $data['info']['detectedSource'];
                }

            } catch (\Exception $e) {
                continue;
            }
        }

        return null;
    }

    /**
     * Return supported languages.
     */
    /** @return array<string, string> */ public function getSupportedLanguages(): array
    {
        return self::SUPPORTED_LANGUAGES;
    }
}
