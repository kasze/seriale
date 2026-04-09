<?php

declare(strict_types=1);

namespace App\Services;

use App\Api\TMDbClient;
use App\Core\Logger;
use App\Repositories\ShowRepository;
use DateTimeImmutable;
use DateTimeInterface;

final class SimilarShowsService
{
    private const CACHE_TTL_HOURS = 24;
    private const CACHE_VERSION = 3;

    public function __construct(
        private TMDbClient $client,
        private AppSettingsService $settings,
        private ShowRepository $shows,
        private Logger $logger
    ) {
    }

    public function enabled(): bool
    {
        return $this->settings->providerEnabled('tmdb') && trim((string) $this->settings->get('tmdb_api_key', '')) !== '';
    }

    public function forShow(array $show, int $limit = 6): array
    {
        if (!$this->enabled()) {
            return [
                'recommended' => [],
                'similar' => [],
            ];
        }

        $cached = $this->readCache($show, $limit, false);

        if ($cached !== null) {
            return $cached;
        }

        try {
            $tmdbShow = $this->resolveTmdbShow($show);

            if ($tmdbShow === null || empty($tmdbShow['id'])) {
                return [
                    'recommended' => [],
                    'similar' => [],
                ];
            }

            $currentTitle = mb_strtolower(trim((string) ($show['title'] ?? '')));
            $recommended = $this->buildItems($this->client->recommendations((int) $tmdbShow['id'], $limit * 2), $currentTitle, $limit);
            $similar = $this->buildItems($this->client->similar((int) $tmdbShow['id'], $limit * 2), $currentTitle, $limit);
            $payload = [
                'recommended' => $recommended,
                'similar' => $similar,
            ];

            $providerPayload = is_array($show['provider_payload'] ?? null) ? $show['provider_payload'] : [];
            $providerPayload['tmdb_related_cache'] = [
                'version' => self::CACHE_VERSION,
                'fetched_at' => now()->format(DATE_ATOM),
                'limit' => $limit,
                'data' => $payload,
            ];
            $this->shows->updateProviderPayload((int) $show['id'], $providerPayload);

            return $payload;
        } catch (\Throwable $throwable) {
            $this->logger->warning('TMDb similar shows fetch failed', [
                'show_id' => $show['id'] ?? null,
                'message' => $throwable->getMessage(),
            ]);

            return $this->readCache($show, $limit, true) ?? [
                'recommended' => [],
                'similar' => [],
            ];
        }
    }

    private function buildItems(array $results, string $currentTitle, int $limit): array
    {
        $items = [];
        $detailsCache = [];

        foreach ($results as $result) {
            $title = trim((string) ($result['name'] ?? $result['original_name'] ?? ''));

            if ($title === '' || mb_strtolower($title) === $currentTitle) {
                continue;
            }

            $details = null;

            if (!empty($result['id'])) {
                $resultId = (int) $result['id'];

                if (!array_key_exists($resultId, $detailsCache)) {
                    $detailsCache[$resultId] = $this->client->showDetails($resultId);
                }

                $details = $detailsCache[$resultId];
            }

            $platform = $this->platformLabel($details, $result);
            $searchTitle = $this->searchTitle($details, $result, $title);

            $items[] = [
                'tmdb_id' => !empty($result['id']) ? (string) $result['id'] : null,
                'title' => $title,
                'original_title' => is_array($details) && !empty($details['original_name']) ? (string) $details['original_name'] : (string) ($result['original_name'] ?? $title),
                'year' => !empty($result['first_air_date']) ? substr((string) $result['first_air_date'], 0, 4) : null,
                'summary' => trim((string) ($result['overview'] ?? '')),
                'poster_url' => $this->client->imageUrl($result['poster_path'] ?? null),
                'tmdb_url' => $this->client->showUrl((int) $result['id']),
                'rating' => isset($result['vote_average']) && (float) $result['vote_average'] > 0 ? number_format((float) $result['vote_average'], 1) : null,
                'platform' => $platform,
                'search_query' => $searchTitle,
                'search_url' => path_url('/shows/search?q=' . urlencode($searchTitle)),
            ];

            if (count($items) >= $limit) {
                break;
            }
        }

        return $items;
    }

    private function readCache(array $show, int $limit, bool $allowStale): ?array
    {
        $cache = $show['provider_payload']['tmdb_related_cache'] ?? null;

        if (!is_array($cache) || !isset($cache['fetched_at'], $cache['data']) || !is_array($cache['data'])) {
            return null;
        }

        if ((int) ($cache['version'] ?? 0) !== self::CACHE_VERSION) {
            return null;
        }

        if (($cache['limit'] ?? null) !== $limit) {
            return null;
        }

        $fetchedAt = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, (string) $cache['fetched_at']);

        if ($fetchedAt === false) {
            return null;
        }

        if (!$allowStale && $fetchedAt->modify('+' . self::CACHE_TTL_HOURS . ' hours') <= now()) {
            return null;
        }

        $recommended = is_array($cache['data']['recommended'] ?? null) ? $cache['data']['recommended'] : [];
        $similar = is_array($cache['data']['similar'] ?? null) ? $cache['data']['similar'] : [];

        return [
            'recommended' => $recommended,
            'similar' => $similar,
        ];
    }

    private function platformLabel(?array $details, array $result): ?string
    {
        $networks = $details['networks'] ?? [];

        if (is_array($networks) && isset($networks[0]['name']) && trim((string) $networks[0]['name']) !== '') {
            return trim((string) $networks[0]['name']);
        }

        $originCountry = $result['origin_country'][0] ?? null;

        if (is_string($originCountry) && $originCountry !== '') {
            return $originCountry;
        }

        return null;
    }

    private function searchTitle(?array $details, array $result, string $fallback): string
    {
        $candidates = [
            is_array($details) ? ($details['original_name'] ?? null) : null,
            $result['original_name'] ?? null,
            is_array($details) ? ($details['name'] ?? null) : null,
            $result['name'] ?? null,
            $fallback,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return $fallback;
    }

    public function prefetchTracked(array $shows, int $limit = 6): array
    {
        $results = [
            'prefetched' => 0,
            'skipped' => 0,
            'failed' => [],
        ];

        if (!$this->enabled()) {
            return $results;
        }

        foreach ($shows as $show) {
            if (!is_array($show) || empty($show['id'])) {
                continue;
            }

            try {
                $cached = $this->readCache($show, $limit, false);

                if ($cached !== null) {
                    $results['skipped']++;
                    continue;
                }

                $this->forShow($show, $limit);
                $results['prefetched']++;
            } catch (\Throwable $throwable) {
                $results['failed'][] = [
                    'show_id' => $show['id'] ?? null,
                    'message' => $throwable->getMessage(),
                ];
            }
        }

        return $results;
    }

    private function resolveTmdbShow(array $show): ?array
    {
        if (!empty($show['tmdb_url']) && preg_match('~/tv/(\d+)~', (string) $show['tmdb_url'], $matches) === 1) {
            return ['id' => (int) $matches[1]];
        }

        $imdbId = $this->extractImdbId((string) ($show['imdb_url'] ?? ''));

        if ($imdbId !== null) {
            $result = $this->client->findShowByImdbId($imdbId);

            if ($result !== null) {
                return $result;
            }
        }

        $year = !empty($show['premiered_on']) ? (int) substr((string) $show['premiered_on'], 0, 4) : null;

        return $this->client->searchShow((string) ($show['title'] ?? ''), $year);
    }

    private function extractImdbId(string $imdbUrl): ?string
    {
        if ($imdbUrl === '') {
            return null;
        }

        if (preg_match('~(tt\d{4,12})~', $imdbUrl, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }
}
