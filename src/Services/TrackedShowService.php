<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ShowUserStateRepository;
use App\Repositories\TrackedShowRepository;

final class TrackedShowService
{
    public function __construct(
        private ShowSyncService $sync,
        private \App\Repositories\ShowRepository $shows,
        private TrackedShowRepository $tracked,
        private ShowUserStateRepository $state
    ) {
    }

    public function trackByExternalSource(int $userId, string $provider, string $sourceId): array
    {
        $show = $this->sync->syncFromProvider($provider, $sourceId, true, false);
        $this->tracked->track($userId, (int) $show['id']);
        $this->state->ensure($userId, (int) $show['id']);

        return $show;
    }

    public function trackBySearchQuery(int $userId, string $query, ?int $year = null, ?string $tmdbId = null): array
    {
        $query = trim($query);
        $tmdbId = $tmdbId !== null ? trim($tmdbId) : null;

        if ($query === '') {
            throw new \RuntimeException('Brak tytułu serialu do dodania.');
        }

        if ($tmdbId !== null && $tmdbId !== '') {
            $existing = $this->shows->findByExternalId('tmdb', $tmdbId);

            if ($existing !== null) {
                $this->tracked->track($userId, (int) $existing['id']);
                $this->state->ensure($userId, (int) $existing['id']);

                return $existing;
            }
        }

        $results = $this->sync->search($query);

        if ($results === []) {
            throw new \RuntimeException('Nie udało się znaleźć serialu w TVmaze.');
        }

        $match = $this->pickBestSearchMatch($results, $query, $year);

        if ($match === null || empty($match['source_id'])) {
            throw new \RuntimeException('Nie udało się dobrać pasującego serialu w TVmaze.');
        }

        $show = $this->trackByExternalSource($userId, 'tvmaze', (string) $match['source_id']);

        if ($tmdbId !== null && $tmdbId !== '') {
            $this->shows->replaceExternalId((int) $show['id'], 'tmdb', $tmdbId);
        }

        return $this->shows->findById((int) $show['id']) ?? $show;
    }

    public function markOpened(int $userId, int $showId): void
    {
        $this->state->markOpened($userId, $showId, now()->format(DATE_ATOM));
    }

    public function untrack(int $userId, int $showId): void
    {
        $this->tracked->untrack($userId, $showId);
    }

    private function pickBestSearchMatch(array $results, string $query, ?int $year): ?array
    {
        $normalizedQuery = mb_strtolower(trim($query));

        foreach ($results as $result) {
            $title = mb_strtolower(trim((string) ($result['title'] ?? '')));
            $itemYear = isset($result['year']) && is_numeric($result['year']) ? (int) $result['year'] : null;

            if ($title === $normalizedQuery && ($year === null || $itemYear === null || abs($itemYear - $year) <= 1)) {
                return $result;
            }
        }

        foreach ($results as $result) {
            $itemYear = isset($result['year']) && is_numeric($result['year']) ? (int) $result['year'] : null;

            if ($year !== null && $itemYear !== null && abs($itemYear - $year) <= 1) {
                return $result;
            }
        }

        return $results[0] ?? null;
    }
}
