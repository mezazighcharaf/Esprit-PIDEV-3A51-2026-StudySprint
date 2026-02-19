<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class DictionaryService
{
    public function __construct(private readonly HttpClientInterface $httpClient)
    {
    }

    /**
     * Fetches the definition and phonetic for a word using Free Dictionary API.
     * Returns an array with 'word', 'phonetic', 'definitions' (array of strings) or null on failure.
     */
    public function lookup(string $word): ?array
    {
        $encoded = urlencode(strtolower(trim($word)));

        try {
            $response = $this->httpClient->request(
                'GET',
                "https://api.dictionaryapi.dev/api/v2/entries/en/{$encoded}",
                ['timeout' => 5]
            );

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $data = $response->toArray();

            if (empty($data) || !isset($data[0])) {
                return null;
            }

            $entry = $data[0];

            $phonetic = $entry['phonetic'] ?? null;
            if (!$phonetic && !empty($entry['phonetics'])) {
                foreach ($entry['phonetics'] as $p) {
                    if (!empty($p['text'])) {
                        $phonetic = $p['text'];
                        break;
                    }
                }
            }

            $definitions = [];
            foreach ($entry['meanings'] ?? [] as $meaning) {
                $partOfSpeech = $meaning['partOfSpeech'] ?? '';
                foreach ($meaning['definitions'] ?? [] as $def) {
                    $definitions[] = [
                        'partOfSpeech' => $partOfSpeech,
                        'definition'   => $def['definition'] ?? '',
                        'example'      => $def['example'] ?? null,
                    ];
                    if (count($definitions) >= 5) {
                        break 2;
                    }
                }
            }

            return [
                'word'        => $entry['word'] ?? $word,
                'phonetic'    => $phonetic,
                'definitions' => $definitions,
            ];
        } catch (\Throwable) {
            return null;
        }
    }
}
