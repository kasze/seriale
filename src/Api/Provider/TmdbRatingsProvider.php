<?php

declare(strict_types=1);

namespace App\Api\Provider;

use App\Api\TMDbClient;
use App\Services\AppSettingsService;

final class TmdbRatingsProvider implements RatingsProviderInterface
{
    public function __construct(
        private TMDbClient $client,
        private AppSettingsService $settings
    ) {
    }

    public function name(): string
    {
        return 'tmdb';
    }

    public function enabled(): bool
    {
        return $this->settings->providerEnabled('tmdb') && $this->settings->get('tmdb_api_key', '') !== '';
    }

    public function enrich(array $show): array
    {
        if (!$this->enabled()) {
            return [];
        }

        $year = null;

        if (!empty($show['premiered_on'])) {
            $year = (int) substr((string) $show['premiered_on'], 0, 4);
        }

        $result = $this->client->searchShow((string) $show['title'], $year);

        if ($result === null) {
            return [];
        }

        return [
            'tmdb_rating' => isset($result['vote_average']) ? (float) $result['vote_average'] : null,
            'tmdb_rating_source' => 'TMDb',
            'tmdb_url' => $this->client->showUrl((int) $result['id']),
        ];
    }
}

