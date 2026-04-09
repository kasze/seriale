<?php

declare(strict_types=1);

namespace App\Repositories;

final class UserRepository extends BaseRepository
{
    public function findById(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM ' . $this->table('users') . ' WHERE `id` = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $user = $statement->fetch();

        return $user === false ? null : $user;
    }

    public function findByIdentity(string $identity): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM ' . $this->table('users') . ' WHERE LOWER(`identity`) = LOWER(:identity) LIMIT 1');
        $statement->execute(['identity' => $identity]);
        $user = $statement->fetch();

        return $user === false ? null : $user;
    }

    public function findOrCreateSingleUser(string $identity): array
    {
        $email = str_contains($identity, '@') ? $identity : null;
        $statement = $this->pdo->prepare(
            'INSERT INTO ' . $this->table('users') . ' (`identity`, `email`, `display_name`, `created_at`, `updated_at`) ' .
            'VALUES (:identity, :email, :display_name, NOW(), NOW()) ' .
            'ON DUPLICATE KEY UPDATE `id` = LAST_INSERT_ID(`id`), `updated_at` = `updated_at`'
        );
        $statement->execute([
            'identity' => $identity,
            'email' => $email,
            'display_name' => 'Single User',
        ]);

        return $this->findById((int) $this->pdo->lastInsertId()) ?? throw new \RuntimeException('Nie udalo sie utworzyc uzytkownika.');
    }

    public function markLogin(int $userId, string $timestamp): void
    {
        $statement = $this->pdo->prepare('UPDATE ' . $this->table('users') . ' SET `last_login_at` = :timestamp, `updated_at` = NOW() WHERE `id` = :id');
        $statement->execute(['timestamp' => $this->normalizeDateTime($timestamp), 'id' => $userId]);
    }

    public function updateLastSeen(int $userId, string $timestamp): void
    {
        $statement = $this->pdo->prepare('UPDATE ' . $this->table('users') . ' SET `last_seen_at` = :timestamp, `updated_at` = NOW() WHERE `id` = :id');
        $statement->execute(['timestamp' => $this->normalizeDateTime($timestamp), 'id' => $userId]);
    }

    public function updatePasswordHash(int $userId, string $passwordHash): void
    {
        $statement = $this->pdo->prepare('UPDATE ' . $this->table('users') . ' SET `password_hash` = :password_hash, `updated_at` = NOW() WHERE `id` = :id');
        $statement->execute([
            'password_hash' => $passwordHash,
            'id' => $userId,
        ]);
    }

    public function updateIdentity(int $userId, string $identity): void
    {
        $email = str_contains($identity, '@') ? $identity : null;
        $statement = $this->pdo->prepare('UPDATE ' . $this->table('users') . ' SET `identity` = :identity, `email` = :email, `updated_at` = NOW() WHERE `id` = :id');
        $statement->execute([
            'identity' => $identity,
            'email' => $email,
            'id' => $userId,
        ]);
    }
}
