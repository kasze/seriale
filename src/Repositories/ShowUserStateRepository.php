<?php

declare(strict_types=1);

namespace App\Repositories;

final class ShowUserStateRepository extends BaseRepository
{
    public function ensure(int $userId, int $showId): void
    {
        $statement = $this->pdo->prepare('INSERT IGNORE INTO ' . $this->table('show_user_state') . ' (`user_id`, `show_id`, `created_at`, `updated_at`) VALUES (:user_id, :show_id, NOW(), NOW())');
        $statement->execute([
            'user_id' => $userId,
            'show_id' => $showId,
        ]);
    }

    public function markChecked(int $userId, int $showId, string $timestamp): void
    {
        $this->ensure($userId, $showId);

        $statement = $this->pdo->prepare('UPDATE ' . $this->table('show_user_state') . ' SET `last_checked_at` = :timestamp, `updated_at` = NOW() WHERE `user_id` = :user_id AND `show_id` = :show_id');
        $statement->execute([
            'user_id' => $userId,
            'show_id' => $showId,
            'timestamp' => $this->normalizeDateTime($timestamp),
        ]);
    }

    public function markOpened(int $userId, int $showId, string $timestamp): void
    {
        $this->ensure($userId, $showId);

        $statement = $this->pdo->prepare('UPDATE ' . $this->table('show_user_state') . ' SET `last_opened_at` = :timestamp, `updated_at` = NOW() WHERE `user_id` = :user_id AND `show_id` = :show_id');
        $statement->execute([
            'user_id' => $userId,
            'show_id' => $showId,
            'timestamp' => $this->normalizeDateTime($timestamp),
        ]);
    }

    public function findForUserAndShow(int $userId, int $showId): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM ' . $this->table('show_user_state') . ' WHERE `user_id` = :user_id AND `show_id` = :show_id LIMIT 1');
        $statement->execute([
            'user_id' => $userId,
            'show_id' => $showId,
        ]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }
}
