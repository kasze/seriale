<?php

declare(strict_types=1);

namespace App\Repositories;

final class ShowRepository extends BaseRepository
{
    public function findById(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM ' . $this->table('shows') . ' WHERE `id` = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $show = $statement->fetch();

        return $show === false ? null : $this->decodeRow($show);
    }

    public function findBySource(string $provider, string $sourceId): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM ' . $this->table('shows') . ' WHERE `source_provider` = :provider AND `source_id` = :source_id LIMIT 1');
        $statement->execute(['provider' => $provider, 'source_id' => $sourceId]);
        $show = $statement->fetch();

        return $show === false ? null : $this->decodeRow($show);
    }

    public function findByExternalId(string $provider, string $externalId, string $type = 'show'): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT s.* FROM ' . $this->table('shows') . ' s ' .
            'INNER JOIN ' . $this->table('external_ids') . ' e ON e.`show_id` = s.`id` ' .
            'WHERE e.`provider` = :provider AND e.`external_type` = :external_type AND e.`external_id` = :external_id LIMIT 1'
        );
        $statement->execute([
            'provider' => $provider,
            'external_type' => $type,
            'external_id' => $externalId,
        ]);
        $show = $statement->fetch();

        return $show === false ? null : $this->decodeRow($show);
    }

    public function upsert(array $data): array
    {
        $table = $this->table('shows');
        $statement = $this->pdo->prepare(
            'INSERT INTO ' . $table . ' (
                `source_provider`, `source_id`, `slug`, `title`, `title_sort`, `premiered_on`, `ended_on`, `status`,
                `show_type`, `language`, `summary`, `country_name`, `country_code`, `network_name`, `web_channel_name`,
                `official_site`, `imdb_url`, `tvmaze_url`, `tmdb_url`, `manual_filmweb_url`, `poster_url`, `banner_url`,
                `runtime_minutes`, `average_runtime_minutes`, `genres`, `schedule_time`, `schedule_days`, `tvmaze_rating`,
                `imdb_rating`, `imdb_rating_source`, `rotten_tomatoes_rating`, `rotten_tomatoes_source`, `metacritic_rating`, `metacritic_rating_source`, `tmdb_rating`, `tmdb_rating_source`, `seasons_count`, `episodes_count`,
                `last_episode_air_at`, `last_episode_label`, `next_episode_air_at`, `next_episode_label`,
                `last_synced_at`, `sync_due_at`, `last_sync_status`, `provider_payload`, `created_at`, `updated_at`
            ) VALUES (
                :source_provider, :source_id, :slug, :title, :title_sort, :premiered_on, :ended_on, :status,
                :show_type, :language, :summary, :country_name, :country_code, :network_name, :web_channel_name,
                :official_site, :imdb_url, :tvmaze_url, :tmdb_url, :manual_filmweb_url, :poster_url, :banner_url,
                :runtime_minutes, :average_runtime_minutes, :genres, :schedule_time, :schedule_days, :tvmaze_rating,
                :imdb_rating, :imdb_rating_source, :rotten_tomatoes_rating, :rotten_tomatoes_source, :metacritic_rating, :metacritic_rating_source, :tmdb_rating, :tmdb_rating_source, :seasons_count, :episodes_count,
                :last_episode_air_at, :last_episode_label, :next_episode_air_at, :next_episode_label,
                :last_synced_at, :sync_due_at, :last_sync_status, :provider_payload, NOW(), NOW()
            )
            ON DUPLICATE KEY UPDATE
                `slug` = VALUES(`slug`),
                `title` = VALUES(`title`),
                `title_sort` = VALUES(`title_sort`),
                `premiered_on` = VALUES(`premiered_on`),
                `ended_on` = VALUES(`ended_on`),
                `status` = VALUES(`status`),
                `show_type` = VALUES(`show_type`),
                `language` = VALUES(`language`),
                `summary` = VALUES(`summary`),
                `country_name` = VALUES(`country_name`),
                `country_code` = VALUES(`country_code`),
                `network_name` = VALUES(`network_name`),
                `web_channel_name` = VALUES(`web_channel_name`),
                `official_site` = VALUES(`official_site`),
                `imdb_url` = VALUES(`imdb_url`),
                `tvmaze_url` = VALUES(`tvmaze_url`),
                `tmdb_url` = VALUES(`tmdb_url`),
                `manual_filmweb_url` = COALESCE(VALUES(`manual_filmweb_url`), `manual_filmweb_url`),
                `poster_url` = VALUES(`poster_url`),
                `banner_url` = VALUES(`banner_url`),
                `runtime_minutes` = VALUES(`runtime_minutes`),
                `average_runtime_minutes` = VALUES(`average_runtime_minutes`),
                `genres` = VALUES(`genres`),
                `schedule_time` = VALUES(`schedule_time`),
                `schedule_days` = VALUES(`schedule_days`),
                `tvmaze_rating` = VALUES(`tvmaze_rating`),
                `imdb_rating` = COALESCE(VALUES(`imdb_rating`), `imdb_rating`),
                `imdb_rating_source` = COALESCE(VALUES(`imdb_rating_source`), `imdb_rating_source`),
                `rotten_tomatoes_rating` = COALESCE(VALUES(`rotten_tomatoes_rating`), `rotten_tomatoes_rating`),
                `rotten_tomatoes_source` = COALESCE(VALUES(`rotten_tomatoes_source`), `rotten_tomatoes_source`),
                `metacritic_rating` = COALESCE(VALUES(`metacritic_rating`), `metacritic_rating`),
                `metacritic_rating_source` = COALESCE(VALUES(`metacritic_rating_source`), `metacritic_rating_source`),
                `tmdb_rating` = COALESCE(VALUES(`tmdb_rating`), `tmdb_rating`),
                `tmdb_rating_source` = COALESCE(VALUES(`tmdb_rating_source`), `tmdb_rating_source`),
                `seasons_count` = VALUES(`seasons_count`),
                `episodes_count` = VALUES(`episodes_count`),
                `last_episode_air_at` = VALUES(`last_episode_air_at`),
                `last_episode_label` = VALUES(`last_episode_label`),
                `next_episode_air_at` = VALUES(`next_episode_air_at`),
                `next_episode_label` = VALUES(`next_episode_label`),
                `last_synced_at` = VALUES(`last_synced_at`),
                `sync_due_at` = VALUES(`sync_due_at`),
                `last_sync_status` = VALUES(`last_sync_status`),
                `provider_payload` = VALUES(`provider_payload`),
                `updated_at` = NOW()'
        );
        $statement->execute([
            'source_provider' => $data['source_provider'],
            'source_id' => $data['source_id'],
            'slug' => $data['slug'],
            'title' => $data['title'],
            'title_sort' => $data['title_sort'],
            'premiered_on' => $data['premiered_on'],
            'ended_on' => $data['ended_on'],
            'status' => $data['status'],
            'show_type' => $data['show_type'],
            'language' => $data['language'],
            'summary' => $data['summary'],
            'country_name' => $data['country_name'],
            'country_code' => $data['country_code'],
            'network_name' => $data['network_name'],
            'web_channel_name' => $data['web_channel_name'],
            'official_site' => $data['official_site'],
            'imdb_url' => $data['imdb_url'],
            'tvmaze_url' => $data['tvmaze_url'],
            'tmdb_url' => $data['tmdb_url'],
            'manual_filmweb_url' => $data['manual_filmweb_url'],
            'poster_url' => $data['poster_url'],
            'banner_url' => $data['banner_url'],
            'runtime_minutes' => $data['runtime_minutes'],
            'average_runtime_minutes' => $data['average_runtime_minutes'],
            'genres' => $this->encodeJson($data['genres']),
            'schedule_time' => $data['schedule_time'],
            'schedule_days' => $this->encodeJson($data['schedule_days']),
            'tvmaze_rating' => $data['tvmaze_rating'],
            'imdb_rating' => $data['imdb_rating'],
            'imdb_rating_source' => $data['imdb_rating_source'],
            'rotten_tomatoes_rating' => $data['rotten_tomatoes_rating'] ?? null,
            'rotten_tomatoes_source' => $data['rotten_tomatoes_source'] ?? null,
            'metacritic_rating' => $data['metacritic_rating'] ?? null,
            'metacritic_rating_source' => $data['metacritic_rating_source'] ?? null,
            'tmdb_rating' => $data['tmdb_rating'],
            'tmdb_rating_source' => $data['tmdb_rating_source'],
            'seasons_count' => $data['seasons_count'],
            'episodes_count' => $data['episodes_count'],
            'last_episode_air_at' => $this->normalizeDateTime($data['last_episode_air_at']),
            'last_episode_label' => $data['last_episode_label'],
            'next_episode_air_at' => $this->normalizeDateTime($data['next_episode_air_at']),
            'next_episode_label' => $data['next_episode_label'],
            'last_synced_at' => $this->normalizeDateTime($data['last_synced_at']),
            'sync_due_at' => $this->normalizeDateTime($data['sync_due_at']),
            'last_sync_status' => $data['last_sync_status'],
            'provider_payload' => $this->encodeJson($data['provider_payload']),
        ]);

        return $this->findBySource((string) $data['source_provider'], (string) $data['source_id']) ?? throw new \RuntimeException('Nie udało się zapisać serialu.');
    }

    public function replaceExternalId(int $showId, string $provider, string $externalId, string $type = 'show', array $meta = []): void
    {
        if ($externalId === '') {
            return;
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO ' . $this->table('external_ids') . ' (`show_id`, `provider`, `external_type`, `external_id`, `meta`) ' .
            'VALUES (:show_id, :provider, :external_type, :external_id, :meta) ' .
            'ON DUPLICATE KEY UPDATE `external_id` = VALUES(`external_id`), `meta` = VALUES(`meta`)'
        );
        $statement->execute([
            'show_id' => $showId,
            'provider' => $provider,
            'external_type' => $type,
            'external_id' => $externalId,
            'meta' => $this->encodeJson($meta),
        ]);
    }

    public function updateRatings(
        int $showId,
        ?float $imdbRating,
        ?string $imdbSource,
        ?string $rottenTomatoesRating,
        ?string $rottenTomatoesSource,
        ?int $metacriticRating,
        ?string $metacriticSource,
        ?array $providerPayload = null
    ): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE ' . $this->table('shows') . ' SET ' .
            '`imdb_rating` = COALESCE(:imdb_rating, `imdb_rating`), ' .
            '`imdb_rating_source` = COALESCE(:imdb_rating_source, `imdb_rating_source`), ' .
            '`rotten_tomatoes_rating` = COALESCE(:rotten_tomatoes_rating, `rotten_tomatoes_rating`), ' .
            '`rotten_tomatoes_source` = COALESCE(:rotten_tomatoes_source, `rotten_tomatoes_source`), ' .
            '`metacritic_rating` = COALESCE(:metacritic_rating, `metacritic_rating`), ' .
            '`metacritic_rating_source` = COALESCE(:metacritic_rating_source, `metacritic_rating_source`), ' .
            '`provider_payload` = COALESCE(:provider_payload, `provider_payload`), ' .
            '`updated_at` = NOW() WHERE `id` = :id'
        );
        $statement->execute([
            'id' => $showId,
            'imdb_rating' => $imdbRating,
            'imdb_rating_source' => $imdbSource,
            'rotten_tomatoes_rating' => $rottenTomatoesRating,
            'rotten_tomatoes_source' => $rottenTomatoesSource,
            'metacritic_rating' => $metacriticRating,
            'metacritic_rating_source' => $metacriticSource,
            'provider_payload' => $providerPayload === null ? null : $this->encodeJson($providerPayload),
        ]);
    }

    public function updateProviderPayload(int $showId, array $providerPayload): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE ' . $this->table('shows') . ' SET `provider_payload` = :provider_payload, `updated_at` = NOW() WHERE `id` = :id'
        );
        $statement->execute([
            'id' => $showId,
            'provider_payload' => $this->encodeJson($providerPayload),
        ]);
    }

    public function listDueForSync(int $limit = 10): array
    {
        $statement = $this->pdo->prepare(
            'SELECT DISTINCT s.* FROM ' . $this->table('shows') . ' s ' .
            'INNER JOIN ' . $this->table('tracked_shows') . ' t ON t.`show_id` = s.`id` ' .
            'WHERE s.`sync_due_at` IS NULL OR s.`sync_due_at` <= NOW() ' .
            'ORDER BY (s.`sync_due_at` IS NULL) DESC, s.`sync_due_at` ASC, s.`updated_at` ASC LIMIT :limit'
        );
        $statement->bindValue('limit', $limit, \PDO::PARAM_INT);
        $statement->execute();

        return array_map(fn (array $row) => $this->decodeRow($row), $statement->fetchAll());
    }

    private function decodeRow(array $row): array
    {
        $row['genres'] = json_decode((string) ($row['genres'] ?? '[]'), true) ?? [];
        $row['schedule_days'] = json_decode((string) ($row['schedule_days'] ?? '[]'), true) ?? [];
        $row['provider_payload'] = json_decode((string) ($row['provider_payload'] ?? '{}'), true) ?? [];

        return $row;
    }
}
