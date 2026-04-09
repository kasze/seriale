<?php

declare(strict_types=1);

namespace App\Repositories;

final class AppSettingsRepository extends BaseRepository
{
    public function all(): array
    {
        $statement = $this->pdo->query('SELECT `key`, `value`, `type`, `group_name`, `updated_at` FROM ' . $this->table('app_settings') . ' ORDER BY `group_name`, `key`');

        return $statement->fetchAll();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $statement = $this->pdo->prepare('SELECT `value`, `type` FROM ' . $this->table('app_settings') . ' WHERE `key` = :key LIMIT 1');
        $statement->execute(['key' => $key]);
        $row = $statement->fetch();

        if ($row === false) {
            return $default;
        }

        return $this->castValue($row['value'], $row['type']);
    }

    public function upsertMany(array $items): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO ' . $this->table('app_settings') . ' (`key`, `value`, `type`, `group_name`, `updated_at`) ' .
            'VALUES (:key, :value, :type, :group_name, NOW()) ' .
            'ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `type` = VALUES(`type`), `group_name` = VALUES(`group_name`), `updated_at` = NOW()'
        );

        foreach ($items as $key => $item) {
            $statement->execute([
                'key' => $key,
                'value' => $item['value'] ?? null,
                'type' => $item['type'] ?? 'string',
                'group_name' => $item['group_name'] ?? 'general',
            ]);
        }
    }

    public function pairs(): array
    {
        $pairs = [];

        foreach ($this->all() as $row) {
            $pairs[$row['key']] = $this->castValue($row['value'], $row['type']);
        }

        return $pairs;
    }

    private function castValue(?string $value, string $type): mixed
    {
        return match ($type) {
            'bool' => in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true),
            'int' => is_numeric($value) ? (int) $value : 0,
            'json' => json_decode((string) $value, true) ?? [],
            default => $value,
        };
    }
}
