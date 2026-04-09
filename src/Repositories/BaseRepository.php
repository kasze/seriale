<?php

declare(strict_types=1);

namespace App\Repositories;

use DateTimeImmutable;
use DateTimeZone;
use PDO;

abstract class BaseRepository
{
    protected DateTimeZone $timezone;

    public function __construct(protected PDO $pdo, protected string $tablePrefix = '')
    {
        $this->timezone = new DateTimeZone(date_default_timezone_get());
    }

    protected function encodeJson(mixed $value): string
    {
        return json_encode($value ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
    }

    protected function table(string $name): string
    {
        return '`' . str_replace('`', '', $this->tablePrefix . $name) . '`';
    }

    protected function normalizeDateTime(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return (new DateTimeImmutable($value))
            ->setTimezone($this->timezone)
            ->format('Y-m-d H:i:s');
    }
}
