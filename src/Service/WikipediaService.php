<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class WikipediaService
{
    public function __construct(private readonly HttpClientInterface $httpClient)
    {
    }

    /**
     * Fetches a French Wikipedia summary for the given title.
     * Returns an array with 'title', 'extract', 'thumbnail', 'url' or null on failure.
     */
    public function getSummary(string $title): ?array
    {
        $encoded = urlencode($title);

        try {
            $response = $this->httpClient->request(
                'GET',
                "https://fr.wikipedia.org/api/rest_v1/page/summary/{$encoded}",
                [
                    'timeout' => 5,
                    'headers' => [
                        'User-Agent' => 'StudySprint/1.0 (educational-app)',
                    ],
                ]
            );

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $data = $response->toArray();

            return [
                'title'     => $data['title'] ?? $title,
                'extract'   => $data['extract'] ?? null,
                'thumbnail' => $data['thumbnail']['source'] ?? null,
                'url'       => $data['content_urls']['desktop']['page'] ?? null,
            ];
        } catch (\Throwable) {
            return null;
        }
    }
}
