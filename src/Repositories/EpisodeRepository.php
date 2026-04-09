<?php

declare(strict_types=1);

namespace App\Repositories;

final class EpisodeRepository extends BaseRepository
{
    public function replaceForShow(int $showId, array $episodes, array $seasonIdMap = []): void
    {
        $delete = $this->pdo->prepare('DELETE FROM ' . $this->table('episodes') . ' WHERE `show_id` = :show_id');
        $delete->execute(['show_id' => $showId]);

        $statement = $this->pdo->prepare(
            'INSERT INTO ' . $this->table('episodes') . ' (
                `show_id`, `season_id`, `source_provider`, `source_id`, `season_number`, `episode_number`,
                `episode_type`, `name`, `summary`, `airdate`, `airtime`, `airstamp`, `runtime_minutes`,
                `image_url`, `is_special`, `provider_payload`, `created_at`, `updated_at`
            ) VALUES (
                :show_id, :season_id, :source_provider, :source_id, :season_number, :episode_number,
                :episode_type, :name, :summary, :airdate, :airtime, :airstamp, :runtime_minutes,
                :image_url, :is_special, :provider_payload, NOW(), NOW()
            )'
        );

        foreach ($episodes as $episode) {
            $statement->execute([
                'show_id' => $showId,
                'season_id' => $seasonIdMap[$episode['season_source_id']] ?? null,
                'source_provider' => $episode['source_provider'],
                'source_id' => $episode['source_id'],
                'season_number' => $episode['season_number'],
                'episode_number' => $episode['episode_number'],
                'episode_type' => $episode['episode_type'],
                'name' => $episode['name'],
                'summary' => $episode['summary'],
                'airdate' => $episode['airdate'],
                'airtime' => $episode['airtime'],
                'airstamp' => $this->normalizeDateTime($episode['airstamp']),
                'runtime_minutes' => $episode['runtime_minutes'],
                'image_url' => $episode['image_url'],
                'is_special' => $episode['is_special'] ? 1 : 0,
                'provider_payload' => $this->encodeJson($episode['provider_payload']),
            ]);
        }
    }

    public function groupedForShow(int $showId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT e.*, s.`name` AS `season_name` FROM ' . $this->table('episodes') . ' e ' .
            'LEFT JOIN ' . $this->table('seasons') . ' s ON s.`id` = e.`season_id` ' .
            'WHERE e.`show_id` = :show_id ' .
            'ORDER BY (e.`season_number` IS NULL) ASC, e.`season_number` DESC, (e.`episode_number` IS NULL) ASC, e.`episode_number` DESC, (e.`airstamp` IS NULL) ASC, e.`airstamp` DESC, e.`id` DESC'
        );
        $statement->execute(['show_id' => $showId]);

        return $statement->fetchAll();
    }
}
