<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

final class Validator
{
    public static function requireString(array $input, string $key, int $max = 255): string
    {
        $value = trim((string) ($input[$key] ?? ''));

        if ($value === '') {
            throw new InvalidArgumentException("Pole {$key} jest wymagane.");
        }

        if (mb_strlen($value) > $max) {
            throw new InvalidArgumentException("Pole {$key} jest zbyt dlugie.");
        }

        return $value;
    }

    public static function optionalString(array $input, string $key, int $max = 255): ?string
    {
        $value = trim((string) ($input[$key] ?? ''));

        if ($value === '') {
            return null;
        }

        if (mb_strlen($value) > $max) {
            throw new InvalidArgumentException("Pole {$key} jest zbyt dlugie.");
        }

        return $value;
    }

    public static function boolean(array $input, string $key): bool
    {
        return isset($input[$key]) && in_array($input[$key], ['1', 'true', 'on', 'yes'], true);
    }
}

