<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class TranslationService
{
    // MyMemory free translation API — no key required, 1000 req/day
    private const MYMEMORY_URL = 'https://api.mymemory.translated.net/get';

    public function __construct(private readonly HttpClientInterface $httpClient)
    {
    }

    /**
     * Translates text using MyMemory (free, no API key required).
     * Returns the translated string, or null on failure.
     *
     * @param string $text       Text to translate
     * @param string $sourceLang Source language code (e.g. 'fr', 'en', 'auto')
     * @param string $targetLang Target language code (e.g. 'en', 'ar')
     */
    public function translate(string $text, string $sourceLang = 'auto', string $targetLang = 'en'): ?string
    {
        if (trim($text) === '') {
            return null;
        }

        // MyMemory uses "fr|en" format; 'auto' detection not supported → default to 'fr'
        $src = ($sourceLang === 'auto') ? 'fr' : $sourceLang;
        $langPair = $src . '|' . $targetLang;

        try {
            $response = $this->httpClient->request(
                'GET',
                self::MYMEMORY_URL,
                [
                    'timeout' => 8,
                    'query'   => [
                        'q'        => $text,
                        'langpair' => $langPair,
                    ],
                ]
            );

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $data = $response->toArray();

            $translated = $data['responseData']['translatedText'] ?? null;

            // MyMemory returns the original text when it fails
            if ($translated === null || strtolower(trim($translated)) === strtolower(trim($text))) {
                return null;
            }

            return $translated;
        } catch (\Throwable) {
            return null;
        }
    }

    public function getSupportedLanguages(): array
    {
        return [
            'fr' => 'Français',
            'en' => 'English',
            'es' => 'Español',
            'de' => 'Deutsch',
            'ar' => 'العربية',
            'it' => 'Italiano',
            'pt' => 'Português',
            'tr' => 'Türkçe',
            'zh' => '中文',
        ];
    }
}
