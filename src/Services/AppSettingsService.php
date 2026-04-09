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
                'help' => 'Nazwa widoczna w nagłówku i tytule strony.',
                'placeholder' => 'Seriale',
            ],
            [
                'key' => 'app_theme',
                'label' => 'Motyw interfejsu',
                'type' => 'string',
                'group' => 'appearance',
                'input' => 'select',
                'help' => 'Jasny utrzymuje dzienny wygląd. Ciemny przełącza całą aplikację, łącznie z logowaniem i ustawieniami, na nocny wariant.',
                'options' => [
                    ['value' => 'light', 'label' => 'Jasny'],
                    ['value' => 'dark', 'label' => 'Ciemny'],
                ],
            ],
            [
                'key' => 'single_user_identity',
                'label' => 'Login użytkownika',
                'type' => 'string',
                'group' => 'account',
                'input' => 'email',
                'help' => 'Jedyny dozwolony login do aplikacji. Reset hasła zawsze idzie na ten adres.',
                'placeholder' => 'you@example.com',
            ],
            [
                'key' => 'cache_ttl_hours',
                'label' => 'Ważność zapisanych danych',
                'type' => 'int',
                'group' => 'sync',
                'input' => 'number',
                'help' => 'Co ile godzin dane serialu mogą być odświeżane przy wejściu na stronę. Typowe wartości: 6, 12, 24.',
                'suffix' => 'godzin',
                'min' => 1,
            ],
            [
                'key' => 'episode_availability_offset_days',
                'label' => 'Korekta daty emisji',
                'type' => 'int',
                'group' => 'sync',
                'input' => 'number',
                'help' => 'Ile dni dodać do dat z TVmaze przed zapisaniem odcinka. Dla seriali z USA na polskich platformach zwykle zostaw 1. Dla dokładnych dat z API ustaw 0.',
                'suffix' => 'dni',
                'min' => -7,
                'max' => 14,
            ],
            [
                'key' => 'cron_secret',
                'label' => 'Tajny klucz automatycznego odświeżania',
                'type' => 'string',
                'group' => 'sync',
                'input' => 'text',
                'help' => 'Tajny klucz potrzebny do uruchomienia technicznego adresu odświeżania przez hosting albo zewnętrzny automat. Powinien być długi i losowy.',
                'placeholder' => 'np. dlugi-losowy-sekret',
            ],
            [
                'key' => 'provider_tvmaze_enabled',
                'label' => 'TVmaze',
                'type' => 'bool',
                'group' => 'providers',
                'help' => 'Główne źródło seriali, sezonów i odcinków. W praktyce powinno pozostać włączone.',
            ],
            [
                'key' => 'provider_tmdb_enabled',
                'label' => 'TMDb',
                'type' => 'bool',
                'group' => 'providers',
                'help' => 'Dostarcza topki, podobne seriale i polecane tytuły w szczegółach serialu. Wymaga klucza API TMDb.',
            ],
            [
                'key' => 'tmdb_api_key',
                'label' => 'Klucz API TMDb',
                'type' => 'string',
                'group' => 'providers',
                'input' => 'text',
                'help' => 'Wklej klucz API z themoviedb.org. Używany do topek, podobnych seriali i polecanych tytułów.',
                'placeholder' => 'wklej klucz TMDb',
                'nullable' => true,
            ],
            [
                'key' => 'provider_omdb_enabled',
                'label' => 'OMDb',
                'type' => 'bool',
                'group' => 'providers',
                'help' => 'Uzupełnia IMDb, Rotten Tomatoes i Metacritic. Wymaga klucza API OMDb.',
            ],
            [
                'key' => 'omdb_api_key',
                'label' => 'Klucz API OMDb',
                'type' => 'string',
                'group' => 'providers',
                'input' => 'text',
                'help' => 'Wklej klucz API z omdbapi.com. Po zapisaniu i odświeżeniu seriali mogą pojawić się IMDb, Rotten Tomatoes i Metacritic.',
                'placeholder' => 'wklej klucz OMDb',
                'nullable' => true,
            ],
            [
                'key' => 'mail_transport',
                'label' => 'Wysyłka wiadomości',
                'type' => 'string',
                'group' => 'account',
                'input' => 'select',
                'help' => 'Wbudowana wysyłka PHP próbuje wysłać wiadomość. Dziennik aplikacji nie wysyła wiadomości i zapisuje link resetu tylko w lokalnym dzienniku zdarzeń.',
                'options' => [
                    ['value' => 'mail', 'label' => 'Wbudowana wysyłka PHP'],
                    ['value' => 'log', 'label' => 'Dziennik aplikacji'],
                ],
            ],
            [
                'key' => 'mail_from_address',
                'label' => 'Adres nadawcy',
                'type' => 'string',
                'group' => 'account',
                'input' => 'email',
                'help' => 'Adres nadawcy używany przy mailach resetu hasła. Typowo no-reply@twojadomena.pl.',
                'placeholder' => 'no-reply@example.com',
            ],
            [
                'key' => 'mail_from_name',
                'label' => 'Nazwa nadawcy',
                'type' => 'string',
                'group' => 'account',
                'input' => 'text',
                'help' => 'Nazwa wyświetlana w skrzynce pocztowej odbiorcy.',
                'placeholder' => 'Seriale',
            ],
        ];
    }

    public function groups(): array
    {
        return [
            'appearance' => [
                'label' => 'Wygląd i aplikacja',
                'description' => 'Nazwa aplikacji i motyw interfejsu. Tryb publiczny albo deweloperski ustawiasz w pliku .env.',
            ],
            'account' => [
                'label' => 'Dostęp i reset hasła',
                'description' => 'Login jedynego użytkownika oraz ustawienia wiadomości do resetu hasła.',
            ],
            'sync' => [
                'label' => 'Synchronizacja',
                'description' => 'Zapisane dane, korekta dat emisji i zabezpieczenie automatycznego odświeżania.',
            ],
            'providers' => [
                'label' => 'Integracje z API',
                'description' => 'Źródła seriali, rekomendacji i ocen z zewnętrznych serwisów.',
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
                'int' => (string) min(
                    isset($definition['max']) ? (int) $definition['max'] : PHP_INT_MAX,
                    max(isset($definition['min']) ? (int) $definition['min'] : 0, (int) $raw)
                ),
                default => trim((string) $raw),
            };

            if (isset($definition['options'])) {
                $allowed = array_map(static fn (array $option) => (string) $option['value'], $definition['options']);

                if (!in_array($value, $allowed, true)) {
                    throw new InvalidArgumentException("Pole {$definition['label']} ma nieprawidłową wartość.");
                }
            }

            if ($definition['type'] === 'string' && !($definition['nullable'] ?? false) && $value === '') {
                throw new InvalidArgumentException("Pole {$definition['label']} nie może być puste.");
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

    public function episodeAvailabilityOffsetDays(): int
    {
        return max(-7, min(14, (int) $this->get('episode_availability_offset_days', 1)));
    }

    public function timezone(): string
    {
        return (string) $this->get('app_timezone', 'Europe/Warsaw');
    }

    public function appEnv(): string
    {
        return (string) $this->config->get('APP_ENV', 'development');
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
            'episode_availability_offset_days' => (int) $this->config->get('EPISODE_AVAILABILITY_OFFSET_DAYS', 1),
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
