<?php

declare(strict_types=1);

namespace App\Services;

use App\Api\TMDbClient;
use App\Core\Logger;
use DateInterval;
use DateTimeImmutable;

final class TopListsService
{
    private const CACHE_FILE = 'cache/tmdb-top-lists.json';
    private const CACHE_VERSION = 2;

    public function __construct(
        private TMDbClient $client,
        private AppSettingsService $settings,
        private Logger $logger
    ) {
    }

    public function enabled(): bool
    {
        return $this->settings->providerEnabled('tmdb') && trim((string) $this->settings->get('tmdb_api_key', '')) !== '';
    }

    public function lists(int $limit = 12): array
    {
        if (!$this->enabled()) {
            return [];
        }

        $cached = $this->readCache($limit, false);

        if ($cached !== null) {
            return $cached;
        }

        try {
            $lists = [
                [
                    'key' => 'trending_day',
                    'label' => 'Trendujące dziś',
                    'description' => 'Najgorętsze seriale z ostatnich 24 godzin.',
                    'items' => $this->mapItems($this->client->trending('day', $limit)),
                ],
                [
                    'key' => 'trending_week',
                    'label' => 'Trendujące w tygodniu',
                    'description' => 'Najczęściej oglądane i klikane w ostatnich 7 dniach.',
                    'items' => $this->mapItems($this->client->trending('week', $limit)),
                ],
                [
                    'key' => 'popular',
                    'label' => 'Popularne',
                    'description' => 'Najpopularniejsze seriale według TMDb.',
                    'items' => $this->mapItems($this->client->popular($limit)),
                ],
                [
                    'key' => 'top_rated',
                    'label' => 'Najwyżej oceniane',
                    'description' => 'Seriale z najwyższą oceną społeczności TMDb.',
                    'items' => $this->mapItems($this->client->topRated($limit)),
                ],
                [
                    'key' => 'on_the_air',
                    'label' => 'W emisji',
                    'description' => 'Seriale, które mają teraz nowe odcinki.',
                    'items' => $this->mapItems($this->client->onTheAir($limit)),
                ],
                [
                    'key' => 'airing_today',
                    'label' => 'Dziś emitowane',
                    'description' => 'Seriale z odcinkiem emitowanym dziś.',
                    'items' => $this->mapItems($this->client->airingToday($limit)),
                ],
            ];

            $payload = [
                'fetched_at' => now()->format(DATE_ATOM),
                'version' => self::CACHE_VERSION,
                'limit' => $limit,
                'lists' => $lists,
            ];

            $this->writeCache($payload);

            return $lists;
        } catch (\Throwable $throwable) {
            $this->logger->warning('TMDb top lists fetch failed', [
                'message' => $throwable->getMessage(),
            ]);

            return $this->readCache($limit, true) ?? [];
        }
    }

    private function mapItems(array $items): array
    {
        $mapped = [];

        foreach (array_values($items) as $index => $item) {
            $title = trim((string) ($item['name'] ?? $item['original_name'] ?? ''));
            $originalTitle = trim((string) ($item['original_name'] ?? $title));

            if ($title === '') {
                continue;
            }

            $meta = array_values(array_filter([
                $item['first_air_date'] ?? null ? substr((string) $item['first_air_date'], 0, 4) : null,
                $item['origin_country'][0] ?? null,
            ]));

            $mapped[] = [
                'rank' => $index + 1,
                'tmdb_id' => !empty($item['id']) ? (string) $item['id'] : null,
                'title' => $title,
                'original_title' => $originalTitle,
                'search_query' => $originalTitle !== '' ? $originalTitle : $title,
                'year' => $item['first_air_date'] ?? null ? substr((string) $item['first_air_date'], 0, 4) : null,
                'summary' => trim((string) ($item['overview'] ?? '')),
                'poster_url' => $this->client->imageUrl($item['poster_path'] ?? null),
                'rating' => isset($item['vote_average']) && (float) $item['vote_average'] > 0 ? number_format((float) $item['vote_average'], 1) : null,
                'meta' => implode(' · ', $meta),
                'search_url' => path_url('/shows/search?q=' . urlencode($originalTitle !== '' ? $originalTitle : $title)),
                'tmdb_url' => !empty($item['id']) ? $this->client->showUrl((int) $item['id']) : null,
            ];
        }

        return $mapped;
    }

    private function readCache(int $limit, bool $allowStale): ?array
    {
        $path = storage_path(self::CACHE_FILE);

        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        $json = file_get_contents($path);

        if ($json === false) {
            return null;
        }

        $payload = json_decode($json, true);

        if (!is_array($payload)) {
            return null;
        }

        if ((int) ($payload['version'] ?? 0) !== self::CACHE_VERSION) {
            return null;
        }

        if ((int) ($payload['limit'] ?? 0) !== $limit) {
            return null;
        }

        if (!isset($payload['fetched_at'], $payload['lists']) || !is_array($payload['lists'])) {
            return null;
        }

        try {
            $fetchedAt = new DateTimeImmutable((string) $payload['fetched_at']);
        } catch (\Throwable) {
            return null;
        }

        if (!$allowStale && $fetchedAt->add(new DateInterval('P7D')) <= now()) {
            return null;
        }

        return $payload['lists'];
    }

    private function writeCache(array $payload): void
    {
        $path = storage_path(self::CACHE_FILE);
        $directory = dirname($path);

        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        if ($json === false) {
            return;
        }

        $tmp = $path . '.tmp';

        if (@file_put_contents($tmp, $json, LOCK_EX) === false) {
            return;
        }

        @rename($tmp, $path);
    }
}
