<?php

declare(strict_types=1);

namespace App\Repositories;

final class TrackedShowRepository extends BaseRepository
{
    public function track(int $userId, int $showId): void
    {
        $statement = $this->pdo->prepare('INSERT IGNORE INTO ' . $this->table('tracked_shows') . ' (`user_id`, `show_id`, `added_at`) VALUES (:user_id, :show_id, NOW())');
        $statement->execute([
            'user_id' => $userId,
            'show_id' => $showId,
        ]);
    }

    public function untrack(int $userId, int $showId): void
    {
        $statement = $this->pdo->prepare('DELETE FROM ' . $this->table('tracked_shows') . ' WHERE `user_id` = :user_id AND `show_id` = :show_id');
        $statement->execute([
            'user_id' => $userId,
            'show_id' => $showId,
        ]);
    }

    public function isTracked(int $userId, int $showId): bool
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM ' . $this->table('tracked_shows') . ' WHERE `user_id` = :user_id AND `show_id` = :show_id');
        $statement->execute([
            'user_id' => $userId,
            'show_id' => $showId,
        ]);

        return (int) $statement->fetchColumn() > 0;
    }

    public function trackedShowIds(int $userId): array
    {
        $statement = $this->pdo->prepare('SELECT `show_id` FROM ' . $this->table('tracked_shows') . ' WHERE `user_id` = :user_id');
        $statement->execute(['user_id' => $userId]);

        return array_map(static fn (array $row) => (int) $row['show_id'], $statement->fetchAll());
    }

    public function trackedExternalIdMap(int $userId, string $provider, string $type = 'show'): array
    {
        $statement = $this->pdo->prepare(
            'SELECT e.`external_id`, t.`show_id`
             FROM ' . $this->table('tracked_shows') . ' t
             INNER JOIN ' . $this->table('external_ids') . ' e ON e.`show_id` = t.`show_id`
             WHERE t.`user_id` = :user_id
               AND e.`provider` = :provider
               AND e.`external_type` = :external_type'
        );
        $statement->execute([
            'user_id' => $userId,
            'provider' => $provider,
            'external_type' => $type,
        ]);

        $map = [];

        foreach ($statement->fetchAll() as $row) {
            $externalId = trim((string) ($row['external_id'] ?? ''));

            if ($externalId === '') {
                continue;
            }

            $map[$externalId] = (int) ($row['show_id'] ?? 0);
        }

        return $map;
    }

    public function listTrackedWithStats(int $userId, string $sort = 'next'): array
    {
        $orderBy = match ($sort) {
            'title' => 's.`title_sort` ASC, s.`title` ASC',
            'added' => 't.`added_at` DESC, s.`title_sort` ASC',
            default => '(s.`next_episode_air_at` IS NULL) ASC, s.`next_episode_air_at` ASC, s.`title_sort` ASC',
        };

        $sql = '
            SELECT
                t.`added_at`,
                s.*,
                season_choice.`display_season_number`,
                COALESCE(season_stats.`display_season_future_count`, 0) AS `display_season_future_count`,
                COALESCE(season_stats.`display_season_aired_count`, 0) AS `display_season_aired_count`
            FROM ' . $this->table('tracked_shows') . ' t
            INNER JOIN ' . $this->table('shows') . ' s ON s.`id` = t.`show_id`
            LEFT JOIN (
                SELECT
                    e.`show_id`,
                    COALESCE(
                        MAX(CASE WHEN e.`airstamp` > NOW() THEN e.`season_number` END),
                        MAX(e.`season_number`)
                    ) AS `display_season_number`
                FROM ' . $this->table('episodes') . ' e
                INNER JOIN ' . $this->table('tracked_shows') . ' tt
                    ON tt.`show_id` = e.`show_id`
                   AND tt.`user_id` = :season_choice_user_id
                WHERE e.`season_number` IS NOT NULL
                GROUP BY e.`show_id`
            ) season_choice ON season_choice.`show_id` = s.`id`
            LEFT JOIN (
                SELECT
                    e.`show_id`,
                    e.`season_number`,
                    SUM(CASE WHEN e.`airstamp` IS NOT NULL AND e.`airstamp` > NOW() THEN 1 ELSE 0 END) AS `display_season_future_count`,
                    SUM(CASE WHEN e.`airstamp` IS NOT NULL AND e.`airstamp` <= NOW() THEN 1 ELSE 0 END) AS `display_season_aired_count`
                FROM ' . $this->table('episodes') . ' e
                INNER JOIN ' . $this->table('tracked_shows') . ' tt2
                    ON tt2.`show_id` = e.`show_id`
                   AND tt2.`user_id` = :season_stats_user_id
                WHERE e.`season_number` IS NOT NULL
                GROUP BY e.`show_id`, e.`season_number`
            ) season_stats
                ON season_stats.`show_id` = s.`id`
               AND season_stats.`season_number` = season_choice.`display_season_number`
            WHERE t.`user_id` = :user_id
            ORDER BY ' . $orderBy;

        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            'user_id' => $userId,
            'season_choice_user_id' => $userId,
            'season_stats_user_id' => $userId,
        ]);

        return $statement->fetchAll();
    }

    public function listUpcomingEpisodes(int $userId, int $days, bool $todayOnly = false): array
    {
        $safeDays = max(1, $days);
        $condition = $todayOnly
            ? 'DATE(e.`airstamp`) = CURRENT_DATE'
            : 'e.`airstamp` > NOW() AND e.`airstamp` <= DATE_ADD(NOW(), INTERVAL ' . $safeDays . ' DAY)';

        $sql = '
            SELECT
                e.*,
                s.`title`,
                s.`poster_url`,
                s.`status`,
                s.`id` AS `show_id_local`
            FROM ' . $this->table('episodes') . ' e
            INNER JOIN ' . $this->table('shows') . ' s ON s.`id` = e.`show_id`
            INNER JOIN ' . $this->table('tracked_shows') . ' t ON t.`show_id` = s.`id`
            WHERE t.`user_id` = :user_id
              AND e.`airstamp` IS NOT NULL
              AND ' . $condition . '
            ORDER BY e.`airstamp` ASC, s.`title_sort` ASC';

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue('user_id', $userId, \PDO::PARAM_INT);

        $statement->execute();

        return $statement->fetchAll();
    }

    public function listRecentlyAiredEpisodes(int $userId, int $days): array
    {
        $safeDays = max(1, $days);
        $condition = 'e.`airstamp` <= NOW() AND e.`airstamp` >= DATE_SUB(NOW(), INTERVAL ' . $safeDays . ' DAY)';

        return $this->listEpisodesByCondition($userId, $condition, 'e.`airstamp` DESC, s.`title_sort` ASC');
    }

    public function listOlderAiredEpisodes(int $userId, int $days): array
    {
        $safeDays = max(1, $days);
        $condition = 'e.`airstamp` < DATE_SUB(NOW(), INTERVAL ' . $safeDays . ' DAY)';

        return $this->listEpisodesByCondition($userId, $condition, 'e.`airstamp` DESC, s.`title_sort` ASC');
    }

    public function listFutureEpisodesAfter(int $userId, int $days): array
    {
        $safeDays = max(1, $days);
        $condition = 'e.`airstamp` > DATE_ADD(NOW(), INTERVAL ' . $safeDays . ' DAY)';

        return $this->listEpisodesByCondition($userId, $condition, 'e.`airstamp` ASC, s.`title_sort` ASC');
    }

    public function listFutureShowForecastsAfter(int $userId, int $days): array
    {
        $safeDays = max(1, $days);

        $sql = '
            SELECT
                e.*,
                s.`title`,
                s.`poster_url`,
                s.`status`,
                s.`id` AS `show_id_local`
            FROM ' . $this->table('episodes') . ' e
            INNER JOIN ' . $this->table('shows') . ' s ON s.`id` = e.`show_id`
            INNER JOIN ' . $this->table('tracked_shows') . ' t ON t.`show_id` = s.`id`
            INNER JOIN (
                SELECT
                    e2.`show_id`,
                    MIN(e2.`airstamp`) AS `next_future_airstamp`
                FROM ' . $this->table('episodes') . ' e2
                INNER JOIN ' . $this->table('tracked_shows') . ' t2 ON t2.`show_id` = e2.`show_id`
                WHERE t2.`user_id` = :inner_user_id
                  AND e2.`airstamp` IS NOT NULL
                  AND e2.`airstamp` > DATE_ADD(NOW(), INTERVAL ' . $safeDays . ' DAY)
                GROUP BY e2.`show_id`
            ) future ON future.`show_id` = e.`show_id` AND future.`next_future_airstamp` = e.`airstamp`
            WHERE t.`user_id` = :outer_user_id
            ORDER BY e.`airstamp` ASC, s.`title_sort` ASC';

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue('inner_user_id', $userId, \PDO::PARAM_INT);
        $statement->bindValue('outer_user_id', $userId, \PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function listEpisodesBetween(int $userId, string $from, string $to): array
    {
        $sql = '
            SELECT
                e.*,
                s.`title`,
                s.`poster_url`,
                s.`status`,
                s.`id` AS `show_id_local`
            FROM ' . $this->table('episodes') . ' e
            INNER JOIN ' . $this->table('shows') . ' s ON s.`id` = e.`show_id`
            INNER JOIN ' . $this->table('tracked_shows') . ' t ON t.`show_id` = s.`id`
            WHERE t.`user_id` = :user_id
              AND e.`airstamp` IS NOT NULL
              AND e.`airstamp` >= :from_date
              AND e.`airstamp` <= :to_date
            ORDER BY e.`airstamp` ASC, s.`title_sort` ASC';

        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            'user_id' => $userId,
            'from_date' => $this->normalizeDateTime($from),
            'to_date' => $this->normalizeDateTime($to),
        ]);

        return $statement->fetchAll();
    }

    public function listBacklog(int $userId): array
    {
        $sql = '
            SELECT *
            FROM (
                SELECT
                    s.*,
                    t.`added_at`,
                    sus.`last_checked_at`,
                    (
                        SELECT COUNT(*)
                        FROM ' . $this->table('episodes') . ' e
                        WHERE e.`show_id` = s.`id`
                          AND e.`airstamp` IS NOT NULL
                          AND e.`airstamp` <= NOW()
                          AND e.`airstamp` > COALESCE(sus.`last_checked_at`, t.`added_at`)
                    ) AS new_episode_count,
                    (
                        SELECT MAX(e.`airstamp`)
                        FROM ' . $this->table('episodes') . ' e
                        WHERE e.`show_id` = s.`id`
                          AND e.`airstamp` IS NOT NULL
                          AND e.`airstamp` <= NOW()
                          AND e.`airstamp` > COALESCE(sus.`last_checked_at`, t.`added_at`)
                    ) AS latest_new_episode_at
                FROM ' . $this->table('tracked_shows') . ' t
                INNER JOIN ' . $this->table('shows') . ' s ON s.`id` = t.`show_id`
                LEFT JOIN ' . $this->table('show_user_state') . ' sus ON sus.`show_id` = s.`id` AND sus.`user_id` = t.`user_id`
                WHERE t.`user_id` = :user_id
            ) backlog
            WHERE backlog.`new_episode_count` > 0
            ORDER BY backlog.`latest_new_episode_at` DESC';

        $statement = $this->pdo->prepare($sql);
        $statement->execute(['user_id' => $userId]);

        return $statement->fetchAll();
    }

    public function listRecentlyAdded(int $userId, int $limit = 6): array
    {
        $statement = $this->pdo->prepare(
            'SELECT s.*, t.`added_at`
             FROM ' . $this->table('tracked_shows') . ' t
             INNER JOIN ' . $this->table('shows') . ' s ON s.`id` = t.`show_id`
             WHERE t.`user_id` = :user_id
             ORDER BY t.`added_at` DESC
             LIMIT :limit'
        );
        $statement->bindValue('user_id', $userId, \PDO::PARAM_INT);
        $statement->bindValue('limit', $limit, \PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function countNewSinceVisit(int $userId, string $lastSeenAt): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM ' . $this->table('episodes') . ' e
             INNER JOIN ' . $this->table('tracked_shows') . ' t ON t.`show_id` = e.`show_id`
             WHERE t.`user_id` = :user_id
               AND e.`airstamp` IS NOT NULL
               AND e.`airstamp` > :last_seen_at
               AND e.`airstamp` <= NOW()'
        );
        $statement->execute([
            'user_id' => $userId,
            'last_seen_at' => $this->normalizeDateTime($lastSeenAt),
        ]);

        return (int) $statement->fetchColumn();
    }

    private function listEpisodesByCondition(int $userId, string $condition, string $orderBy): array
    {
        $sql = '
            SELECT
                e.*,
                s.`title`,
                s.`poster_url`,
                s.`status`,
                s.`id` AS `show_id_local`
            FROM ' . $this->table('episodes') . ' e
            INNER JOIN ' . $this->table('shows') . ' s ON s.`id` = e.`show_id`
            INNER JOIN ' . $this->table('tracked_shows') . ' t ON t.`show_id` = s.`id`
            WHERE t.`user_id` = :user_id
              AND e.`airstamp` IS NOT NULL
              AND ' . $condition . '
            ORDER BY ' . $orderBy;

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue('user_id', $userId, \PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }
}
