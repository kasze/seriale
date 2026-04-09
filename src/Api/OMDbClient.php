<?php

declare(strict_types=1);

namespace App\Api;

use App\Core\HttpClient;

final class OMDbClient
{
    private const BASE_URL = 'https://www.omdbapi.com/';

    public function __construct(
        private HttpClient $httpClient,
        private string $apiKey
    ) {
    }

    public function byImdbId(string $imdbId): ?array
    {
        if ($this->apiKey === '' || $imdbId === '') {
            return null;
        }

        $query = http_build_query([
            'apikey' => $this->apiKey,
            'i' => $imdbId,
            'plot' => 'short',
        ]);

        $response = $this->httpClient->getJson(self::BASE_URL . '?' . $query);

        if (($response['Response'] ?? 'False') !== 'True') {
            return null;
        }

        return $response;
    }

    public function byTitle(string $title, ?int $year = null): ?array
    {
        if ($this->apiKey === '' || trim($title) === '') {
            return null;
        }

        $query = http_build_query(array_filter([
            'apikey' => $this->apiKey,
            't' => $title,
            'type' => 'series',
            'y' => $year,
            'plot' => 'short',
        ], static fn ($value) => $value !== null && $value !== ''));

        $response = $this->httpClient->getJson(self::BASE_URL . '?' . $query);

        if (($response['Response'] ?? 'False') !== 'True') {
            return null;
        }

        return $response;
    }
}
