<?php

declare(strict_types=1);

namespace App\Services;

use App\Api\Provider\ProviderRegistry;
use App\Repositories\EpisodeRepository;
use App\Repositories\SeasonRepository;
use App\Repositories\ShowRepository;
use App\Repositories\SyncLogRepository;
use DateInterval;
use PDO;

final class ShowSyncService
{
    public function __construct(
        private ProviderRegistry $providers,
        private ShowRepository $shows,
        private SeasonRepository $seasons,
        private EpisodeRepository $episodes,
        private SyncLogRepository $syncLogs,
        private AppSettingsService $settings,
        private PDO $pdo
    ) {
    }

    public function search(string $query): array
    {
        if (!$this->settings->providerEnabled('tvmaze')) {
            return [];
        }

        return $this->providers->showProvider('tvmaze')->search($query);
    }

    public function syncFromProvider(string $provider, string $sourceId, bool $force = false, bool $refreshRatings = true): array
    {
        $existing = $this->shows->findBySource($provider, $sourceId);

        if ($existing !== null && !$force && !$this->needsRefresh($existing)) {
            return $existing;
        }

        $start = microtime(true);
        $payload = $this->providers->showProvider($provider)->fetchShow($sourceId);
        $syncedAt = now()->format(DATE_ATOM);
        $syncDueAt = now()->add(new DateInterval('PT' . $this->settings->cacheTtlHours() . 'H'))->format(DATE_ATOM);
        $payload['show']['last_synced_at'] = $syncedAt;
        $payload['show']['sync_due_at'] = $syncDueAt;
        $payload['show']['last_sync_status'] = 'ok';
        $payload['show']['provider_payload'] = array_replace(
            is_array($existing['provider_payload'] ?? null) ? $existing['provider_payload'] : [],
            is_array($payload['show']['provider_payload'] ?? null) ? $payload['show']['provider_payload'] : []
        );

        $this->pdo->beginTransaction();

        try {
            $show = $this->shows->upsert($payload['show']);

            foreach ($payload['external_ids'] as $externalId) {
                $this->shows->replaceExternalId(
                    (int) $show['id'],
                    $externalId['provider'],
                    $externalId['external_id'],
                    $externalId['external_type'],
                    $externalId['meta'] ?? []
                );
            }

            $seasonMap = $this->seasons->replaceForShow((int) $show['id'], $payload['seasons']);
            $this->episodes->replaceForShow((int) $show['id'], $payload['episodes'], $seasonMap);

            if ($refreshRatings) {
                foreach ($this->providers->ratingsProviders() as $ratingsProvider) {
                    $isOmdb = $ratingsProvider->name() === 'omdb';

                    if ($isOmdb && !empty($show['provider_payload']['omdb_checked_at'])) {
                        continue;
                    }

                    $ratings = $ratingsProvider->enrich($show);
                    $providerPayload = null;

                    if ($isOmdb) {
                        $providerPayload = $show['provider_payload'] ?? [];
                        $providerPayload['omdb_checked_at'] = now()->format(DATE_ATOM);
                        $show['provider_payload'] = $providerPayload;
                    }

                    if ($ratings !== [] || $providerPayload !== null) {
                        $this->shows->updateRatings(
                            (int) $show['id'],
                            $ratings['imdb_rating'] ?? null,
                            $ratings['imdb_rating_source'] ?? null,
                            $ratings['rotten_tomatoes_rating'] ?? null,
                            $ratings['rotten_tomatoes_source'] ?? null,
                            $ratings['metacritic_rating'] ?? null,
                            $ratings['metacritic_rating_source'] ?? null,
                            $providerPayload
                        );
                    }
                }
            }

            $this->pdo->commit();

            $duration = (int) round((microtime(true) - $start) * 1000);
            $this->syncLogs->create((int) $show['id'], $provider, 'show', 'ok', 'Synchronizacja zakonczona powodzeniem.', [
                'source_id' => $sourceId,
            ], $duration);

            return $this->shows->findBySource($provider, $sourceId) ?? $show;
        } catch (\Throwable $throwable) {
            $this->pdo->rollBack();
            $duration = (int) round((microtime(true) - $start) * 1000);
            $this->syncLogs->create($existing['id'] ?? null, $provider, 'show', 'error', $throwable->getMessage(), [
                'source_id' => $sourceId,
            ], $duration);

            if ($existing !== null) {
                return $existing;
            }

            throw $throwable;
        }
    }

    public function refreshLocalShow(int $showId, bool $force = false): array
    {
        $show = $this->shows->findById($showId);

        if ($show === null) {
            throw new \RuntimeException('Serial nie istnieje.');
        }

        return $this->syncFromProvider((string) $show['source_provider'], (string) $show['source_id'], $force);
    }

    public function refreshIfStale(array $show): array
    {
        if ($this->needsRefresh($show)) {
            return $this->refreshLocalShow((int) $show['id'], false);
        }

        return $show;
    }

    public function refreshDueShows(int $limit = 10): array
    {
        $results = [];

        foreach ($this->shows->listDueForSync($limit) as $show) {
            $results[] = $this->refreshLocalShow((int) $show['id'], true);
        }

        return $results;
    }

    public function refreshManyLocalShows(array $showIds, bool $force = true): array
    {
        $results = [
            'refreshed' => [],
            'failed' => [],
        ];

        foreach (array_values(array_unique(array_map('intval', $showIds))) as $showId) {
            if ($showId <= 0) {
                continue;
            }

            try {
                $results['refreshed'][] = $this->refreshLocalShow($showId, $force);
            } catch (\Throwable $throwable) {
                $results['failed'][] = [
                    'show_id' => $showId,
                    'message' => $throwable->getMessage(),
                ];
            }
        }

        return $results;
    }

    public function needsRefresh(?array $show): bool
    {
        if ($show === null) {
            return true;
        }

        if (empty($show['sync_due_at'])) {
            return true;
        }

        return strtotime((string) $show['sync_due_at']) <= time();
    }
}
