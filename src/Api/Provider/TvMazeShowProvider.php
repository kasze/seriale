<?php

declare(strict_types=1);

namespace App\Api\Provider;

use App\Api\TVMazeClient;
use App\Support\Html;
use App\Support\Str;
use DateTimeImmutable;
use DateTimeZone;

final class TvMazeShowProvider implements ShowProviderInterface
{
    public function __construct(
        private TVMazeClient $client,
        private DateTimeZone $timezone
    ) {
    }

    public function name(): string
    {
        return 'tvmaze';
    }

    public function search(string $query): array
    {
        $items = $this->client->searchShows($query);

        return array_map(function (array $row): array {
            $show = $row['show'] ?? [];
            $network = $show['webChannel']['name'] ?? $show['network']['name'] ?? null;
            $country = $show['network']['country']['name'] ?? $show['webChannel']['country']['name'] ?? null;

            return [
                'provider' => 'tvmaze',
                'source_id' => (string) ($show['id'] ?? ''),
                'title' => (string) ($show['name'] ?? ''),
                'year' => isset($show['premiered']) ? substr((string) $show['premiered'], 0, 4) : null,
                'poster_url' => $show['image']['medium'] ?? $show['image']['original'] ?? null,
                'country' => $country,
                'network' => $network,
                'status' => $show['status'] ?? null,
                'summary' => Html::stripSummary($show['summary'] ?? null),
            ];
        }, $items);
    }

    public function fetchShow(string $sourceId): array
    {
        $payload = $this->client->showDetails($sourceId);
        $episodes = $payload['_embedded']['episodes'] ?? [];
        $seasons = $payload['_embedded']['seasons'] ?? [];

        $seasonNumberToSourceId = [];

        foreach ($seasons as $season) {
            if (isset($season['number'], $season['id'])) {
                $seasonNumberToSourceId[(int) $season['number']] = (string) $season['id'];
            }
        }

        [$lastEpisodeAt, $lastEpisodeLabel, $nextEpisodeAt, $nextEpisodeLabel] = $this->deriveEpisodePointers($episodes);
        $network = $payload['network']['name'] ?? null;
        $webChannel = $payload['webChannel']['name'] ?? null;
        $country = $payload['network']['country']['name'] ?? $payload['webChannel']['country']['name'] ?? null;
        $countryCode = $payload['network']['country']['code'] ?? $payload['webChannel']['country']['code'] ?? null;
        $imdbId = $payload['externals']['imdb'] ?? null;

        return [
            'show' => [
                'source_provider' => 'tvmaze',
                'source_id' => (string) $payload['id'],
                'slug' => Str::slug((string) ($payload['name'] ?? 'show')),
                'title' => (string) ($payload['name'] ?? 'Unknown'),
                'title_sort' => mb_strtolower((string) ($payload['name'] ?? '')),
                'premiered_on' => $payload['premiered'] ?? null,
                'ended_on' => $payload['ended'] ?? null,
                'status' => $payload['status'] ?? null,
                'show_type' => $payload['type'] ?? null,
                'language' => $payload['language'] ?? null,
                'summary' => $payload['summary'] ?? null,
                'country_name' => $country,
                'country_code' => $countryCode,
                'network_name' => $network,
                'web_channel_name' => $webChannel,
                'official_site' => $payload['officialSite'] ?? null,
                'imdb_url' => $imdbId ? 'https://www.imdb.com/title/' . $imdbId : null,
                'tvmaze_url' => $payload['_links']['self']['href'] ?? ('https://www.tvmaze.com/shows/' . $payload['id']),
                'tmdb_url' => null,
                'manual_filmweb_url' => null,
                'poster_url' => $payload['image']['original'] ?? $payload['image']['medium'] ?? null,
                'banner_url' => $payload['image']['original'] ?? $payload['image']['medium'] ?? null,
                'runtime_minutes' => $payload['runtime'] ?? null,
                'average_runtime_minutes' => $payload['averageRuntime'] ?? null,
                'genres' => $payload['genres'] ?? [],
                'schedule_time' => $payload['schedule']['time'] ?? null,
                'schedule_days' => $payload['schedule']['days'] ?? [],
                'tvmaze_rating' => $payload['rating']['average'] ?? null,
                'imdb_rating' => null,
                'imdb_rating_source' => null,
                'tmdb_rating' => null,
                'tmdb_rating_source' => null,
                'seasons_count' => count($seasons),
                'episodes_count' => count($episodes),
                'last_episode_air_at' => $lastEpisodeAt,
                'last_episode_label' => $lastEpisodeLabel,
                'next_episode_air_at' => $nextEpisodeAt,
                'next_episode_label' => $nextEpisodeLabel,
                'last_synced_at' => null,
                'sync_due_at' => null,
                'last_sync_status' => 'ok',
                'provider_payload' => [
                    'maze_updated' => $payload['updated'] ?? null,
                ],
            ],
            'external_ids' => array_filter([
                ['provider' => 'tvmaze', 'external_type' => 'show', 'external_id' => (string) $payload['id'], 'meta' => []],
                $imdbId ? ['provider' => 'imdb', 'external_type' => 'show', 'external_id' => $imdbId, 'meta' => []] : null,
            ]),
            'seasons' => array_map(function (array $season): array {
                return [
                    'source_provider' => 'tvmaze',
                    'source_id' => (string) $season['id'],
                    'season_number' => $season['number'] ?? null,
                    'name' => $season['name'] ?? (($season['number'] ?? null) ? 'Season ' . $season['number'] : null),
                    'episode_order' => $season['episodeOrder'] ?? null,
                    'premiere_date' => $season['premiereDate'] ?? null,
                    'end_date' => $season['endDate'] ?? null,
                    'image_url' => $season['image']['original'] ?? $season['image']['medium'] ?? null,
                    'summary' => $season['summary'] ?? null,
                    'provider_payload' => $season,
                ];
            }, $seasons),
            'episodes' => array_map(function (array $episode) use ($seasonNumberToSourceId): array {
                return [
                    'source_provider' => 'tvmaze',
                    'source_id' => (string) $episode['id'],
                    'season_source_id' => $seasonNumberToSourceId[(int) ($episode['season'] ?? 0)] ?? null,
                    'season_number' => $episode['season'] ?? null,
                    'episode_number' => $episode['number'] ?? null,
                    'episode_type' => $episode['type'] ?? null,
                    'name' => $episode['name'] ?? null,
                    'summary' => $episode['summary'] ?? null,
                    'airdate' => $episode['airdate'] ?? null,
                    'airtime' => $episode['airtime'] ?? null,
                    'airstamp' => $this->polishAvailabilityStamp($episode['airstamp'] ?? null),
                    'runtime_minutes' => $episode['runtime'] ?? null,
                    'image_url' => $episode['image']['original'] ?? $episode['image']['medium'] ?? null,
                    'is_special' => (bool) ($episode['type'] === 'significant_special' || $episode['type'] === 'insignificant_special'),
                    'provider_payload' => $episode,
                ];
            }, $episodes),
        ];
    }

    private function deriveEpisodePointers(array $episodes): array
    {
        $now = new DateTimeImmutable('now', $this->timezone);
        $last = null;
        $next = null;

        foreach ($episodes as $episode) {
            $airstamp = $this->polishAvailabilityStamp($episode['airstamp'] ?? null);

            if ($airstamp === null) {
                continue;
            }

            $stamp = new DateTimeImmutable($airstamp);
            $label = sprintf('S%02dE%02d', (int) ($episode['season'] ?? 0), (int) ($episode['number'] ?? 0));

            if ($stamp <= $now) {
                $last = [$airstamp, $label];
                continue;
            }

            $next = [$airstamp, $label];
            break;
        }

        return [
            $last[0] ?? null,
            $last[1] ?? null,
            $next[0] ?? null,
            $next[1] ?? null,
        ];
    }

    private function polishAvailabilityStamp(?string $airstamp): ?string
    {
        if ($airstamp === null || trim($airstamp) === '') {
            return null;
        }

        return (new DateTimeImmutable($airstamp))
            ->modify('+1 day')
            ->format(DATE_ATOM);
    }
}
