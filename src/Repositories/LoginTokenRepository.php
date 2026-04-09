<?php

declare(strict_types=1);

namespace App\Repositories;

final class LoginTokenRepository extends BaseRepository
{
    public function create(int $userId, string $identity, string $tokenHash, string $codeHash, string $expiresAt, string $ip, string $purpose = 'login'): array
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO ' . $this->table('login_tokens') . ' (`user_id`, `identity`, `purpose`, `token_hash`, `code_hash`, `expires_at`, `requested_ip`) ' .
            'VALUES (:user_id, :identity, :purpose, :token_hash, :code_hash, :expires_at, :requested_ip)'
        );
        $statement->execute([
            'user_id' => $userId,
            'identity' => $identity,
            'purpose' => $purpose,
            'token_hash' => $tokenHash,
            'code_hash' => $codeHash,
            'expires_at' => $this->normalizeDateTime($expiresAt),
            'requested_ip' => $ip,
        ]);

        return $this->findById((int) $this->pdo->lastInsertId()) ?? throw new \RuntimeException('Nie udało się zapisać tokenu logowania.');
    }

    public function consumeByTokenHash(string $tokenHash): ?array
    {
        return $this->consumeOne('SELECT * FROM ' . $this->table('login_tokens') . " WHERE `purpose` = 'login' AND `token_hash` = :value AND `consumed_at` IS NULL AND `expires_at` > NOW() ORDER BY `id` DESC LIMIT 1 FOR UPDATE", [
            'value' => $tokenHash,
        ]);
    }

    public function consumeByIdentityAndCodeHash(string $identity, string $codeHash): ?array
    {
        return $this->consumeOne(
            'SELECT * FROM ' . $this->table('login_tokens') . " WHERE `purpose` = 'login' AND LOWER(`identity`) = LOWER(:identity) AND `code_hash` = :code_hash AND `consumed_at` IS NULL AND `expires_at` > NOW() ORDER BY `id` DESC LIMIT 1 FOR UPDATE",
            [
                'identity' => $identity,
                'code_hash' => $codeHash,
            ]
        );
    }

    public function findValidByTokenHashAndPurpose(string $tokenHash, string $purpose): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM ' . $this->table('login_tokens') . ' WHERE `purpose` = :purpose AND `token_hash` = :value AND `consumed_at` IS NULL AND `expires_at` > NOW() ORDER BY `id` DESC LIMIT 1'
        );
        $statement->execute([
            'purpose' => $purpose,
            'value' => $tokenHash,
        ]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    public function consumeByTokenHashAndPurpose(string $tokenHash, string $purpose): ?array
    {
        return $this->consumeOne(
            'SELECT * FROM ' . $this->table('login_tokens') . ' WHERE `purpose` = :purpose AND `token_hash` = :value AND `consumed_at` IS NULL AND `expires_at` > NOW() ORDER BY `id` DESC LIMIT 1 FOR UPDATE',
            [
                'purpose' => $purpose,
                'value' => $tokenHash,
            ]
        );
    }

    public function purgeExpired(): void
    {
        $this->pdo->exec('DELETE FROM ' . $this->table('login_tokens') . " WHERE `expires_at` < DATE_SUB(NOW(), INTERVAL 2 DAY)");
    }

    private function consumeOne(string $selectSql, array $params): ?array
    {
        $this->pdo->beginTransaction();

        try {
            $select = $this->pdo->prepare($selectSql);
            $select->execute($params);
            $row = $select->fetch();

            if ($row === false) {
                $this->pdo->rollBack();

                return null;
            }

            $update = $this->pdo->prepare('UPDATE ' . $this->table('login_tokens') . ' SET `consumed_at` = NOW() WHERE `id` = :id AND `consumed_at` IS NULL');
            $update->execute(['id' => $row['id']]);

            if ($update->rowCount() !== 1) {
                $this->pdo->rollBack();

                return null;
            }

            $this->pdo->commit();

            return $this->findById((int) $row['id']);
        } catch (\Throwable $throwable) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $throwable;
        }
    }

    private function findById(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM ' . $this->table('login_tokens') . ' WHERE `id` = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }
}
