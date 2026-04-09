<?php

declare(strict_types=1);

namespace App\Api\Provider;

use App\Api\OMDbClient;
use App\Services\AppSettingsService;

final class OmdbRatingsProvider implements RatingsProviderInterface
{
    public function __construct(
        private OMDbClient $client,
        private AppSettingsService $settings
    ) {
    }

    public function name(): string
    {
        return 'omdb';
    }

    public function enabled(): bool
    {
        return $this->settings->providerEnabled('omdb') && $this->settings->get('omdb_api_key', '') !== '';
    }

    public function enrich(array $show): array
    {
        if (!$this->enabled()) {
            return [];
        }

        $imdbUrl = $show['imdb_url'] ?? null;
        $payload = null;

        if (is_string($imdbUrl) && preg_match('#/title/(tt[0-9]+)#', $imdbUrl, $matches)) {
            $payload = $this->client->byImdbId($matches[1]);
        }

        if ($payload === null) {
            $year = null;

            if (!empty($show['premiered_on'])) {
                $year = (int) substr((string) $show['premiered_on'], 0, 4);
            }

            $payload = $this->client->byTitle((string) ($show['title'] ?? ''), $year);
        }

        if ($payload === null) {
            return [];
        }

        $rottenTomatoes = null;

        foreach (($payload['Ratings'] ?? []) as $rating) {
            if (($rating['Source'] ?? null) === 'Rotten Tomatoes') {
                $rottenTomatoes = (string) ($rating['Value'] ?? '');
                break;
            }
        }

        $metacritic = $payload['Metascore'] ?? null;
        $metacriticValue = ($metacritic === null || $metacritic === 'N/A') ? null : (int) $metacritic;

        return [
            'imdb_rating' => (!empty($payload['imdbRating']) && $payload['imdbRating'] !== 'N/A') ? (float) $payload['imdbRating'] : null,
            'imdb_rating_source' => (!empty($payload['imdbRating']) && $payload['imdbRating'] !== 'N/A') ? 'OMDb / IMDb' : null,
            'rotten_tomatoes_rating' => $rottenTomatoes !== '' ? $rottenTomatoes : null,
            'rotten_tomatoes_source' => $rottenTomatoes !== '' ? 'OMDb / Rotten Tomatoes' : null,
            'metacritic_rating' => $metacriticValue,
            'metacritic_rating_source' => $metacriticValue !== null ? 'OMDb / Metacritic' : null,
        ];
    }
}
