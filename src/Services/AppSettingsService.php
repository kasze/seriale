<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Logger;
use App\Repositories\AppSettingsRepository;
use InvalidArgumentException;

final class AppSettingsService
{
    private ?array $cache = null;

    public function __construct(
        private AppSettingsRepository $repository,
        private Config $config,
        private Logger $logger
    ) {
    }

    public function all(): array
    {
        return $this->load();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $settings = $this->load();

        return $settings[$key] ?? $default;
    }

    public function definitions(): array
    {
        return [
            [
                'key' => 'app_name',
                'label' => 'Nazwa aplikacji',
                'type' => 'string',
                'group' => 'appearance',
                'input' => 'text',
                'help' => 'Nazwa widoczna w naglowku i tytule strony.',
                'placeholder' => 'Seriale',
            ],
            [
                'key' => 'app_theme',
                'label' => 'Motyw interfejsu',
                'type' => 'string',
                'group' => 'appearance',
                'input' => 'select',
                'help' => 'Jasny utrzymuje obecny wyglad. Ciemny przelacza cala aplikacje, lacznie z logowaniem i ustawieniami, na nocny wariant.',
                'options' => [
                    ['value' => 'light', 'label' => 'Jasny'],
                    ['value' => 'dark', 'label' => 'Ciemny'],
                ],
            ],
            [
                'key' => 'app_env',
                'label' => 'Tryb aplikacji',
                'type' => 'string',
                'group' => 'appearance',
                'input' => 'select',
                'help' => 'Production ukrywa debug i traktuje logowanie oraz maile jako produkcyjne. Development zostawia wiecej pomocniczych fallbackow.',
                'options' => [
                    ['value' => 'production', 'label' => 'Production'],
                    ['value' => 'development', 'label' => 'Development'],
                ],
            ],
            [
                'key' => 'app_timezone',
                'label' => 'Strefa czasowa',
                'type' => 'string',
                'group' => 'appearance',
                'input' => 'select',
                'help' => 'Wplywa na daty premier, relatywne etykiety czasu i sesje.',
                'options' => [
                    ['value' => 'Europe/Warsaw', 'label' => 'Europe/Warsaw'],
                    ['value' => 'UTC', 'label' => 'UTC'],
                    ['value' => 'Europe/London', 'label' => 'Europe/London'],
                    ['value' => 'America/New_York', 'label' => 'America/New_York'],
                ],
            ],
            [
                'key' => 'single_user_identity',
                'label' => 'Login uzytkownika',
                'type' => 'string',
                'group' => 'account',
                'input' => 'email',
                'help' => 'Jedyny dozwolony login do aplikacji. Reset hasla zawsze idzie na ten adres.',
                'placeholder' => 'you@example.com',
            ],
            [
                'key' => 'cache_ttl_hours',
                'label' => 'TTL cache',
                'type' => 'int',
                'group' => 'sync',
                'input' => 'number',
                'help' => 'Co ile godzin dane serialu moga byc odswiezane lazy refresh. Typowe wartosci: 6, 12, 24.',
                'suffix' => 'godzin',
                'min' => 1,
            ],
            [
                'key' => 'cron_secret',
                'label' => 'Sekret endpointu cron',
                'type' => 'string',
                'group' => 'sync',
                'input' => 'text',
                'help' => 'Tajny klucz do recznego lub cyklicznego odpalania /cron/sync. Powinien byc dlugi i losowy.',
                'placeholder' => 'np. dlugi-losowy-sekret',
            ],
            [
                'key' => 'provider_tvmaze_enabled',
                'label' => 'TVmaze',
                'type' => 'bool',
                'group' => 'providers',
                'help' => 'Glowny provider seriali, sezonow i odcinkow. W praktyce powinien pozostac wlaczony.',
            ],
            [
                'key' => 'provider_tmdb_enabled',
                'label' => 'TMDb',
                'type' => 'bool',
                'group' => 'providers',
                'help' => 'Dostarcza podobne seriale i rekomendacje w szczegolach serialu. Wymaga klucza API TMDb.',
            ],
            [
                'key' => 'tmdb_api_key',
                'label' => 'Klucz API TMDb',
                'type' => 'string',
                'group' => 'providers',
                'input' => 'text',
                'help' => 'Wklej klucz API z themoviedb.org. Uzywany do sekcji podobnych seriali.',
                'placeholder' => 'wklej klucz TMDb',
                'nullable' => true,
            ],
            [
                'key' => 'provider_omdb_enabled',
                'label' => 'OMDb',
                'type' => 'bool',
                'group' => 'providers',
                'help' => 'Uzupelnia IMDb, Rotten Tomatoes i Metacritic. Wymaga klucza API OMDb.',
            ],
            [
                'key' => 'omdb_api_key',
                'label' => 'Klucz API OMDb',
                'type' => 'string',
                'group' => 'providers',
                'input' => 'text',
                'help' => 'Wklej klucz API z omdbapi.com. Po zapisaniu i odswiezeniu seriali pojawia sie IMDb, Rotten Tomatoes i Metacritic.',
                'placeholder' => 'wklej klucz OMDb',
                'nullable' => true,
            ],
            [
                'key' => 'mail_transport',
                'label' => 'Transport maila',
                'type' => 'string',
                'group' => 'account',
                'input' => 'select',
                'help' => 'Mail wysyla prawdziwy email przez wbudowane mail(). Log nie wysyla nic i tylko zapisuje link resetu w logach / fallbackach.',
                'options' => [
                    ['value' => 'mail', 'label' => 'mail'],
                    ['value' => 'log', 'label' => 'log'],
                ],
            ],
            [
                'key' => 'mail_from_address',
                'label' => 'Adres nadawcy',
                'type' => 'string',
                'group' => 'account',
                'input' => 'email',
                'help' => 'Adres From uzywany przy mailach resetu hasla. Typowo no-reply@twojadomena.pl.',
                'placeholder' => 'no-reply@example.com',
            ],
            [
                'key' => 'mail_from_name',
                'label' => 'Nazwa nadawcy',
                'type' => 'string',
                'group' => 'account',
                'input' => 'text',
                'help' => 'Nazwa wyswietlana w skrzynce pocztowej odbiorcy.',
                'placeholder' => 'Seriale',
            ],
        ];
    }

    public function groups(): array
    {
        return [
            'appearance' => [
                'label' => 'Wyglad i aplikacja',
                'description' => 'Nazwa, motyw, strefa czasowa i tryb pracy calej aplikacji.',
            ],
            'account' => [
                'label' => 'Dostep i reset hasla',
                'description' => 'Login single-user oraz ustawienia techniczne maila do resetu hasla.',
            ],
            'sync' => [
                'label' => 'Synchronizacja',
                'description' => 'Jak dlugo trzymac cache i jak zabezpieczyc reczne odpalanie crona.',
            ],
            'providers' => [
                'label' => 'Integracje z API',
                'description' => 'Zrodla seriali, rekomendacji i ocen z zewnetrznych serwisow.',
            ],
        ];
    }

    public function save(array $input): void
    {
        $definitions = [];

        foreach ($this->definitions() as $definition) {
            $definitions[$definition['key']] = $definition;
        }

        $payload = [];

        foreach ($definitions as $key => $definition) {
            $raw = $input[$key] ?? null;

            $value = match ($definition['type']) {
                'bool' => isset($input[$key]) ? '1' : '0',
                'int' => (string) max(1, (int) $raw),
                default => trim((string) $raw),
            };

            if (isset($definition['options'])) {
                $allowed = array_map(static fn (array $option) => (string) $option['value'], $definition['options']);

                if (!in_array($value, $allowed, true)) {
                    throw new InvalidArgumentException("Pole {$definition['label']} ma nieprawidlowa wartosc.");
                }
            }

            if ($definition['type'] === 'string' && !($definition['nullable'] ?? false) && $value === '') {
                throw new InvalidArgumentException("Pole {$definition['label']} nie moze byc puste.");
            }

            $payload[$key] = [
                'value' => $value,
                'type' => $definition['type'],
                'group_name' => $definition['group'],
            ];
        }

        $this->repository->upsertMany($payload);
        $this->cache = null;
    }

    public function providerEnabled(string $provider): bool
    {
        return (bool) $this->get('provider_' . strtolower($provider) . '_enabled', false);
    }

    public function cacheTtlHours(): int
    {
        return max(1, (int) $this->get('cache_ttl_hours', 12));
    }

    public function timezone(): string
    {
        return (string) $this->get('app_timezone', 'Europe/Warsaw');
    }

    public function appEnv(): string
    {
        return (string) $this->get('app_env', 'development');
    }

    public function theme(): string
    {
        $theme = (string) $this->get('app_theme', 'light');

        return in_array($theme, ['light', 'dark'], true) ? $theme : 'light';
    }

    public function isDevelopment(): bool
    {
        return $this->appEnv() !== 'production';
    }

    public function singleUserIdentity(): string
    {
        return mb_strtolower(trim((string) $this->get('single_user_identity', '')));
    }

    public function mailTransport(): string
    {
        return (string) $this->get('mail_transport', 'log');
    }

    private function load(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $defaults = [
            'app_name' => (string) $this->config->get('APP_NAME', 'Seriale'),
            'app_env' => (string) $this->config->get('APP_ENV', 'development'),
            'app_timezone' => (string) $this->config->get('APP_TIMEZONE', 'Europe/Warsaw'),
            'app_theme' => (string) $this->config->get('APP_THEME', 'light'),
            'single_user_identity' => (string) $this->config->get('SINGLE_USER_IDENTITY', 'you@example.com'),
            'cache_ttl_hours' => (int) $this->config->get('CACHE_TTL_HOURS', 12),
            'provider_tvmaze_enabled' => bool_value($this->config->get('TVMAZE_ENABLED', 'true')),
            'provider_omdb_enabled' => bool_value($this->config->get('OMDB_ENABLED', 'false')),
            'omdb_api_key' => (string) $this->config->get('OMDB_API_KEY', ''),
            'mail_transport' => (string) $this->config->get('MAIL_TRANSPORT', 'log'),
            'mail_from_address' => (string) $this->config->get('MAIL_FROM_ADDRESS', 'no-reply@example.com'),
            'mail_from_name' => (string) $this->config->get('MAIL_FROM_NAME', 'Seriale'),
            'cron_secret' => (string) $this->config->get('CRON_SECRET', 'change-me-too'),
        ];

        try {
            $this->cache = array_merge($defaults, $this->repository->pairs());
        } catch (\Throwable $throwable) {
            $this->logger->warning('Cannot load app settings from database yet', ['message' => $throwable->getMessage()]);
            $this->cache = $defaults;
        }

        return $this->cache;
    }
}
