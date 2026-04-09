<?php

declare(strict_types=1);

use App\Core\Request;
use App\Core\Response;
use App\Support\DateHelper;
use App\Services\AuthService;

function base_path(string $path = ''): string
{
    $base = dirname(__DIR__);

    return $path === '' ? $base : $base . '/' . ltrim($path, '/');
}

function storage_path(string $path = ''): string
{
    $base = base_path('storage');

    return $path === '' ? $base : $base . '/' . ltrim($path, '/');
}

function public_path(string $path = ''): string
{
    $base = base_path('public');

    return $path === '' ? $base : $base . '/' . ltrim($path, '/');
}

function app(?string $id = null): mixed
{
    /** @var App\Core\Container $container */
    $container = $GLOBALS['__app_container'];

    if ($id === null) {
        return $container;
    }

    return $container->get($id);
}

function request(): Request
{
    return app(Request::class);
}

function response(): Response
{
    return app(Response::class);
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function asset(string $path): string
{
    $normalized = ltrim($path, '/');
    $url = rtrim((string) app('config')->get('app.url', ''), '/') . '/assets/' . $normalized;
    $absolutePath = public_path('assets/' . $normalized);

    if (!is_file($absolutePath)) {
        return $url;
    }

    $version = (string) filemtime($absolutePath);

    return $url . '?v=' . rawurlencode($version);
}

function url(string $path = ''): string
{
    $base = rtrim((string) app('config')->get('app.url', ''), '/');
    $path = ltrim($path, '/');

    return $path === '' ? $base : $base . '/' . $path;
}

function path_url(string $path = ''): string
{
    $basePath = rtrim((string) app('config')->get('app.base_path', ''), '/');
    $path = '/' . ltrim($path, '/');

    if ($path === '/') {
        return $basePath === '' ? '/' : $basePath . '/';
    }

    return ($basePath === '' ? '' : $basePath) . $path;
}

function csrf_token(): string
{
    return app(App\Core\Csrf::class)->token();
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function old(string $key, mixed $default = ''): mixed
{
    static $old = null;
    $old ??= app(App\Core\Session::class)->getFlash('old_input', []);

    return $old[$key] ?? $default;
}

function flash(string $key, mixed $default = null): mixed
{
    return app(App\Core\Session::class)->getFlash($key, $default);
}

function now(?DateTimeZone $timezone = null): DateTimeImmutable
{
    return new DateTimeImmutable('now', $timezone ?: app('timezone'));
}

function format_date(?string $datetime, bool $withTime = false): string
{
    return DateHelper::formatDateTime($datetime, app('timezone'), $withTime);
}

function format_airing_date(?string $datetime, ?string $airtime = null): string
{
    return format_date($datetime, trim((string) $airtime) !== '');
}

function relative_date(?string $datetime): string
{
    return DateHelper::relativeLabel($datetime, app('timezone'));
}

function countdown_label(?string $datetime): string
{
    return DateHelper::countdownLabel($datetime, app('timezone'));
}

function upcoming_bucket(?string $datetime): string
{
    return DateHelper::classifyUpcoming($datetime, app('timezone'));
}

function translate_show_status(?string $status): string
{
    return match (mb_strtolower(trim((string) $status))) {
        'running' => 'W emisji',
        'ended' => 'Zakonczony',
        'to be determined' => 'Do ustalenia',
        'in development' => 'W przygotowaniu',
        'development' => 'W przygotowaniu',
        'pilot' => 'Pilot',
        'hiatus' => 'Przerwa',
        'stopped' => 'Wstrzymany',
        'canceled', 'cancelled' => 'Skasowany',
        default => trim((string) $status) !== '' ? (string) $status : 'Nieznany',
    };
}

function translate_app_env(?string $env): string
{
    return match (mb_strtolower(trim((string) $env))) {
        'production' => 'produkcyjny',
        'development' => 'deweloperski',
        'staging' => 'testowy',
        default => trim((string) $env) !== '' ? (string) $env : 'nieznany',
    };
}

function translate_health_status(?string $status): string
{
    return match (mb_strtolower(trim((string) $status))) {
        'ok' => 'dziala',
        'error' => 'blad',
        default => trim((string) $status) !== '' ? (string) $status : 'nieznany',
    };
}

function current_user(): ?array
{
    return app(AuthService::class)->currentUser();
}

function is_authenticated(): bool
{
    return app(AuthService::class)->check();
}

function filmweb_search_url(string $title): string
{
    return 'https://www.filmweb.pl/search?q=' . urlencode($title);
}

function tpb_episode_search_url(string $showTitle, ?int $seasonNumber, ?int $episodeNumber): string
{
    $query = trim(mb_strtolower($showTitle));

    if ($seasonNumber !== null && $episodeNumber !== null) {
        $query .= ' ' . sprintf('s%02de%02d', $seasonNumber, $episodeNumber);
    }

    return 'https://thepiratebay.org/search.php?q=' . urlencode($query) . '&cat=0';
}

function btdig_episode_search_url(string $showTitle, ?int $seasonNumber, ?int $episodeNumber): string
{
    $query = trim(mb_strtolower($showTitle));

    if ($seasonNumber !== null && $episodeNumber !== null) {
        $query .= ' ' . sprintf('s%02de%02d', $seasonNumber, $episodeNumber);
    }

    return 'https://en.btdig.com/search?q=' . urlencode($query);
}

function truncate_text(?string $text, int $limit = 180): string
{
    if ($text === null) {
        return '';
    }

    $text = trim(strip_tags($text));

    if (mb_strlen($text) <= $limit) {
        return $text;
    }

    return mb_substr($text, 0, $limit - 1) . '…';
}

function bool_value(mixed $value): bool
{
    if (is_bool($value)) {
        return $value;
    }

    return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
}
