<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class TranslationService
{
    private const LIBRETRANSLATE_URL = 'https://libretranslate.com/translate';

    public function __construct(private readonly HttpClientInterface $httpClient)
    {
    }

    /**
     * Translates text using LibreTranslate.
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

        try {
            $response = $this->httpClient->request(
                'POST',
                self::LIBRETRANSLATE_URL,
                [
                    'timeout' => 8,
                    'headers' => ['Content-Type' => 'application/json'],
                    'json' => [
                        'q'      => $text,
                        'source' => $sourceLang,
                        'target' => $targetLang,
                        'format' => 'text',
                    ],
                ]
            );

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $data = $response->toArray();

            return $data['translatedText'] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }
}
