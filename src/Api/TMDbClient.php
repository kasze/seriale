<?php

declare(strict_types=1);

namespace App\Api;

use App\Core\HttpClient;

final class TMDbClient
{
    private const BASE_URL = 'https://api.themoviedb.org/3';

    public function __construct(
        private HttpClient $httpClient,
        private string $apiKey
    ) {
    }

    public function searchShow(string $title, ?int $year = null): ?array
    {
        if ($this->apiKey === '') {
            return null;
        }

        $response = $this->httpClient->getJson(self::BASE_URL . '/search/tv?' . $this->query([
            'query' => $title,
            'first_air_date_year' => $year,
            'language' => 'pl-PL',
        ]));
        $first = $response['results'][0] ?? null;

        return is_array($first) ? $first : null;
    }

    public function findShowByImdbId(string $imdbId): ?array
    {
        if ($this->apiKey === '' || $imdbId === '') {
            return null;
        }

        $response = $this->httpClient->getJson(self::BASE_URL . '/find/' . rawurlencode($imdbId) . '?' . $this->query([
            'external_source' => 'imdb_id',
            'language' => 'pl-PL',
        ]));
        $first = $response['tv_results'][0] ?? null;

        return is_array($first) ? $first : null;
    }

    public function recommendations(int|string $id, int $limit = 6): array
    {
        return $this->listForShow('/tv/' . rawurlencode((string) $id) . '/recommendations', $limit);
    }

    public function similar(int|string $id, int $limit = 6): array
    {
        return $this->listForShow('/tv/' . rawurlencode((string) $id) . '/similar', $limit);
    }

    public function trending(string $window = 'week', int $limit = 10): array
    {
        $window = $window === 'day' ? 'day' : 'week';

        return $this->list('/trending/tv/' . $window, $limit);
    }

    public function popular(int $limit = 10): array
    {
        return $this->list('/tv/popular', $limit);
    }

    public function topRated(int $limit = 10): array
    {
        return $this->list('/tv/top_rated', $limit);
    }

    public function onTheAir(int $limit = 10): array
    {
        return $this->list('/tv/on_the_air', $limit);
    }

    public function airingToday(int $limit = 10): array
    {
        return $this->list('/tv/airing_today', $limit);
    }

    public function showUrl(int|string $id): string
    {
        return 'https://www.themoviedb.org/tv/' . $id;
    }

    public function imageUrl(?string $path, string $size = 'w342'): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        return 'https://image.tmdb.org/t/p/' . $size . '/' . ltrim($path, '/');
    }

    public function showDetails(int|string $id): ?array
    {
        if ($this->apiKey === '') {
            return null;
        }

        return $this->httpClient->getJson(self::BASE_URL . '/tv/' . rawurlencode((string) $id) . '?' . $this->query([
            'language' => 'pl-PL',
        ]));
    }

    private function listForShow(string $path, int $limit): array
    {
        if ($this->apiKey === '') {
            return [];
        }

        $response = $this->httpClient->getJson(self::BASE_URL . $path . '?' . $this->query([
            'language' => 'pl-PL',
        ]));

        return array_slice(array_values($response['results'] ?? []), 0, $limit);
    }

    private function list(string $path, int $limit): array
    {
        if ($this->apiKey === '') {
            return [];
        }

        $response = $this->httpClient->getJson(self::BASE_URL . $path . '?' . $this->query([
            'language' => 'pl-PL',
        ]));

        return array_slice(array_values($response['results'] ?? []), 0, $limit);
    }

    private function query(array $params): string
    {
        return http_build_query(array_filter([
            'api_key' => $this->apiKey,
            ...$params,
        ], static fn ($value) => $value !== null && $value !== ''));
    }
}
