<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ShowRepository;
use App\Repositories\TrackedShowRepository;

final class DashboardService
{
    public function __construct(
        private TrackedShowRepository $tracked,
        private ShowRepository $shows
    ) {
    }

    public function build(int $userId, ?string $lastSeenAt, string $sort = 'next'): array
    {
        return [
            'today' => $this->tracked->listUpcomingEpisodes($userId, 1, true),
            'upcoming' => $this->tracked->listUpcomingEpisodes($userId, 7, false),
            'recently_aired' => $this->tracked->listRecentlyAiredEpisodes($userId, 7),
            'older_aired' => $this->tracked->listOlderAiredEpisodes($userId, 7),
            'future_later' => $this->tracked->listFutureShowForecastsAfter($userId, 7),
            'tracked' => $this->tracked->listTrackedWithStats($userId, $sort),
            'new_since_visit' => $lastSeenAt ? $this->tracked->countNewSinceVisit($userId, $lastSeenAt) : 0,
        ];
    }
}
