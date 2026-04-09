<?php

declare(strict_types=1);

namespace App\Repositories;

final class SeasonRepository extends BaseRepository
{
    public function replaceForShow(int $showId, array $seasons): array
    {
        $delete = $this->pdo->prepare('DELETE FROM ' . $this->table('seasons') . ' WHERE `show_id` = :show_id');
        $delete->execute(['show_id' => $showId]);

        $statement = $this->pdo->prepare(
            'INSERT INTO ' . $this->table('seasons') . ' (
                `show_id`, `source_provider`, `source_id`, `season_number`, `name`, `episode_order`,
                `premiere_date`, `end_date`, `image_url`, `summary`, `provider_payload`, `created_at`, `updated_at`
            ) VALUES (
                :show_id, :source_provider, :source_id, :season_number, :name, :episode_order,
                :premiere_date, :end_date, :image_url, :summary, :provider_payload, NOW(), NOW()
            )'
        );

        $map = [];

        foreach ($seasons as $season) {
            $statement->execute([
                'show_id' => $showId,
                'source_provider' => $season['source_provider'],
                'source_id' => $season['source_id'],
                'season_number' => $season['season_number'],
                'name' => $season['name'],
                'episode_order' => $season['episode_order'],
                'premiere_date' => $season['premiere_date'],
                'end_date' => $season['end_date'],
                'image_url' => $season['image_url'],
                'summary' => $season['summary'],
                'provider_payload' => $this->encodeJson($season['provider_payload']),
            ]);
            $map[$season['source_id']] = (int) $this->pdo->lastInsertId();
        }

        return $map;
    }

    public function groupedForShow(int $showId): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM ' . $this->table('seasons') . ' WHERE `show_id` = :show_id ORDER BY (`season_number` IS NULL) ASC, `season_number` DESC, `id` DESC');
        $statement->execute(['show_id' => $showId]);

        return $statement->fetchAll();
    }
}
