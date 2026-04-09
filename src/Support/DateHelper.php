<?php

declare(strict_types=1);

namespace App\Support;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use IntlDateFormatter;

final class DateHelper
{
    private const DAY = 86400;
    private const WEEK = 7 * self::DAY;
    private const MONTH = 30 * self::DAY;
    private const YEAR = 365 * self::DAY;

    public static function formatDateTime(?string $datetime, DateTimeZone $timezone, bool $withTime = false): string
    {
        if ($datetime === null || trim($datetime) === '') {
            return 'Brak danych';
        }

        $date = new DateTimeImmutable($datetime);
        $date = $date->setTimezone($timezone);

        if (class_exists(IntlDateFormatter::class)) {
            $formatter = new IntlDateFormatter(
                'pl_PL',
                IntlDateFormatter::MEDIUM,
                $withTime ? IntlDateFormatter::SHORT : IntlDateFormatter::NONE,
                $timezone->getName()
            );

            return $formatter->format($date) ?: $date->format($withTime ? 'Y-m-d H:i' : 'Y-m-d');
        }

        return $date->format($withTime ? 'Y-m-d H:i' : 'Y-m-d');
    }

    public static function relativeLabel(?string $datetime, DateTimeZone $timezone): string
    {
        if ($datetime === null || trim($datetime) === '') {
            return 'Brak terminu';
        }

        $target = new DateTimeImmutable($datetime);
        $target = $target->setTimezone($timezone);
        $today = new DateTimeImmutable('today', $timezone);
        $targetDay = $target->setTime(0, 0);
        $days = (int) $today->diff($targetDay)->format('%r%a');

        return match (true) {
            $days === 0 => 'dzisiaj',
            $days === 1 => 'jutro',
            $days === -1 => 'wczoraj',
            $days > 1 => self::formatRelativeDistance($days, true),
            default => self::formatRelativeDistance(abs($days), false),
        };
    }

    public static function countdownLabel(?string $datetime, DateTimeZone $timezone): string
    {
        if ($datetime === null || trim($datetime) === '') {
            return 'Brak zapowiedzi';
        }

        $target = new DateTimeImmutable($datetime);
        $target = $target->setTimezone($timezone);
        $now = new DateTimeImmutable('now', $timezone);

        if ($target <= $now) {
            return 'Juz po premierze';
        }

        $diff = $now->diff($target);
        $seconds = max(0, $target->getTimestamp() - $now->getTimestamp());

        if ($seconds >= self::WEEK) {
            return self::formatRelativeDistance((int) floor($seconds / self::DAY), true);
        }

        $hours = ($diff->days * 24) + $diff->h;

        if ($hours >= 1) {
            return 'za ' . $hours . ' h';
        }

        return 'za ' . max(1, $diff->i) . ' min';
    }

    private static function formatRelativeDistance(int $days, bool $future): string
    {
        $seconds = max(0, $days) * self::DAY;

        if ($seconds >= self::YEAR) {
            $value = (int) floor($seconds / self::YEAR);
            $unit = self::plural($value, 'rok', 'lata', 'lat');
        } elseif ($seconds >= self::MONTH) {
            $value = (int) floor($seconds / self::MONTH);
            $unit = self::plural($value, 'miesiac', 'miesiace', 'miesiecy');
        } elseif ($seconds >= self::WEEK) {
            $value = (int) floor($seconds / self::WEEK);
            $unit = self::plural($value, 'tydzień', 'tygodnie', 'tygodni');
        } else {
            $value = $days;
            $unit = self::plural($value, 'dzien', 'dni', 'dni');
        }

        return $future ? 'za ' . $value . ' ' . $unit : $value . ' ' . $unit . ' temu';
    }

    private static function plural(int $value, string $one, string $few, string $many): string
    {
        $mod10 = $value % 10;
        $mod100 = $value % 100;

        if ($value === 1) {
            return $one;
        }

        if ($mod10 >= 2 && $mod10 <= 4 && !($mod100 >= 12 && $mod100 <= 14)) {
            return $few;
        }

        return $many;
    }

    public static function classifyUpcoming(?string $datetime, DateTimeZone $timezone): string
    {
        if ($datetime === null || trim($datetime) === '') {
            return 'none';
        }

        $target = new DateTimeImmutable($datetime);
        $target = $target->setTimezone($timezone);
        $today = new DateTimeImmutable('today', $timezone);
        $days = (int) $today->diff($target->setTime(0, 0))->format('%r%a');

        return match (true) {
            $days <= 0 => 'today',
            $days <= 7 => 'week',
            default => 'later',
        };
    }
}
