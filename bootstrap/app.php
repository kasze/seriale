<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\HealthController;
use App\Controllers\SettingsController;
use App\Controllers\ShowController;
use App\Controllers\TopController;
use App\Core\Config;
use App\Core\Container;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\HttpClient;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;
use App\Api\OMDbClient;
use App\Api\TMDbClient;
use App\Api\TVMazeClient;
use App\Api\Provider\OmdbRatingsProvider;
use App\Api\Provider\ProviderRegistry;
use App\Api\Provider\TvMazeShowProvider;
use App\Repositories\AppSettingsRepository;
use App\Repositories\EpisodeRepository;
use App\Repositories\LoginTokenRepository;
use App\Repositories\SeasonRepository;
use App\Repositories\ShowRepository;
use App\Repositories\ShowUserStateRepository;
use App\Repositories\SyncLogRepository;
use App\Repositories\TrackedShowRepository;
use App\Repositories\UserRepository;
use App\Services\AppSettingsService;
use App\Services\AuthService;
use App\Services\DashboardService;
use App\Services\MailerService;
use App\Services\ShowSyncService;
use App\Services\SimilarShowsService;
use App\Services\TopListsService;
use App\Services\TrackedShowService;

require __DIR__ . '/autoload.php';

$config = Config::fromEnv(base_path('.env'), [
    'app' => [
        'name' => 'Seriale',
        'env' => 'development',
        'debug' => true,
        'url' => 'http://localhost:8000',
        'base_path' => '',
        'timezone' => 'Europe/Warsaw',
        'secret' => 'change-me',
    ],
    'session' => [
        'name' => 'seriale_session',
    ],
    'db' => [
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => '3306',
        'name' => '',
        'user' => '',
        'pass' => '',
        'charset' => 'utf8mb4',
        'table_prefix' => 'seriale_',
        'sslmode' => 'prefer',
    ],
]);

$config->set('app.name', (string) $config->get('APP_NAME', $config->get('app.name')));
$config->set('app.env', (string) $config->get('APP_ENV', $config->get('app.env')));
$config->set('app.debug', bool_value($config->get('APP_DEBUG', $config->get('app.debug'))));
$config->set('app.url', rtrim((string) $config->get('APP_URL', $config->get('app.url')), '/'));
$config->set('app.base_path', rtrim((string) $config->get('APP_BASE_PATH', parse_url((string) $config->get('APP_URL', $config->get('app.url')), PHP_URL_PATH) ?: ''), '/'));
$config->set('app.timezone', (string) $config->get('APP_TIMEZONE', $config->get('app.timezone')));
$config->set('app.secret', (string) $config->get('APP_SECRET', $config->get('app.secret')));
$config->set('session.name', (string) $config->get('SESSION_NAME', $config->get('session.name')));
$config->set('db.driver', (string) $config->get('DB_DRIVER', $config->get('db.driver')));
$config->set('db.host', (string) $config->get('DB_HOST', $config->get('db.host')));
$config->set('db.port', (string) $config->get('DB_PORT', $config->get('db.port')));
$config->set('db.name', (string) $config->get('DB_NAME', $config->get('db.name')));
$config->set('db.user', (string) $config->get('DB_USER', $config->get('db.user')));
$config->set('db.pass', (string) $config->get('DB_PASS', $config->get('db.pass')));
$config->set('db.charset', (string) $config->get('DB_CHARSET', $config->get('db.charset')));
$config->set('db.table_prefix', (string) $config->get('DB_TABLE_PREFIX', $config->get('db.table_prefix')));
$config->set('db.sslmode', (string) $config->get('DB_SSLMODE', $config->get('db.sslmode')));

$container = new Container();
$GLOBALS['__app_container'] = $container;

$container->set('config', $config);
$container->set('timezone', new DateTimeZone((string) $config->get('app.timezone', 'Europe/Warsaw')));
$container->singleton(Session::class, static fn () => new Session($config));
$container->singleton(Csrf::class, static fn (Container $container) => new Csrf($container->get(Session::class)));
$container->singleton(Logger::class, static fn () => new Logger(storage_path('logs/app.log')));
$container->singleton(Database::class, static fn (Container $container) => new Database(new Config([
    'DB_DRIVER' => $config->get('db.driver'),
    'DB_HOST' => $config->get('db.host'),
    'DB_PORT' => $config->get('db.port'),
    'DB_NAME' => $config->get('db.name'),
    'DB_USER' => $config->get('db.user'),
    'DB_PASS' => $config->get('db.pass'),
    'DB_CHARSET' => $config->get('db.charset'),
    'DB_SSLMODE' => $config->get('db.sslmode'),
    'APP_TIMEZONE' => $container->get('timezone')->getName(),
])));
$container->singleton(\PDO::class, static fn (Container $container) => $container->get(Database::class)->connect());
$container->singleton(View::class, static fn () => new View(base_path('views')));
$container->singleton(Request::class, static fn () => Request::capture((string) $config->get('app.base_path', '')));
$container->singleton(Response::class, static fn () => new Response());
$container->singleton(HttpClient::class, static fn (Container $container) => new HttpClient($container->get(Logger::class)));
$container->singleton(AppSettingsRepository::class, static fn (Container $container) => new AppSettingsRepository($container->get(\PDO::class), (string) $container->get('config')->get('db.table_prefix', '')));
$container->singleton(UserRepository::class, static fn (Container $container) => new UserRepository($container->get(\PDO::class), (string) $container->get('config')->get('db.table_prefix', '')));
$container->singleton(LoginTokenRepository::class, static fn (Container $container) => new LoginTokenRepository($container->get(\PDO::class), (string) $container->get('config')->get('db.table_prefix', '')));
$container->singleton(ShowRepository::class, static fn (Container $container) => new ShowRepository($container->get(\PDO::class), (string) $container->get('config')->get('db.table_prefix', '')));
$container->singleton(SeasonRepository::class, static fn (Container $container) => new SeasonRepository($container->get(\PDO::class), (string) $container->get('config')->get('db.table_prefix', '')));
$container->singleton(EpisodeRepository::class, static fn (Container $container) => new EpisodeRepository($container->get(\PDO::class), (string) $container->get('config')->get('db.table_prefix', '')));
$container->singleton(TrackedShowRepository::class, static fn (Container $container) => new TrackedShowRepository($container->get(\PDO::class), (string) $container->get('config')->get('db.table_prefix', '')));
$container->singleton(ShowUserStateRepository::class, static fn (Container $container) => new ShowUserStateRepository($container->get(\PDO::class), (string) $container->get('config')->get('db.table_prefix', '')));
$container->singleton(SyncLogRepository::class, static fn (Container $container) => new SyncLogRepository($container->get(\PDO::class), (string) $container->get('config')->get('db.table_prefix', '')));
$container->singleton(AppSettingsService::class, static fn (Container $container) => new AppSettingsService(
    $container->get(AppSettingsRepository::class),
    $container->get('config'),
    $container->get(Logger::class)
));
try {
    $resolvedTimezone = new DateTimeZone($container->get(AppSettingsService::class)->timezone());
    $container->set('timezone', $resolvedTimezone);
    date_default_timezone_set($resolvedTimezone->getName());
} catch (Throwable) {
    date_default_timezone_set((string) $config->get('app.timezone', 'Europe/Warsaw'));
}
$container->singleton(TVMazeClient::class, static fn (Container $container) => new TVMazeClient($container->get(HttpClient::class)));
$container->singleton(TMDbClient::class, static fn (Container $container) => new TMDbClient(
    $container->get(HttpClient::class),
    (string) $container->get(AppSettingsService::class)->get('tmdb_api_key', '')
));
$container->singleton(OMDbClient::class, static fn (Container $container) => new OMDbClient(
    $container->get(HttpClient::class),
    (string) $container->get(AppSettingsService::class)->get('omdb_api_key', '')
));
$container->singleton(TvMazeShowProvider::class, static fn (Container $container) => new TvMazeShowProvider(
    $container->get(TVMazeClient::class),
    $container->get('timezone'),
    $container->get(AppSettingsService::class)
));
$container->singleton(OmdbRatingsProvider::class, static fn (Container $container) => new OmdbRatingsProvider(
    $container->get(OMDbClient::class),
    $container->get(AppSettingsService::class)
));
$container->singleton(ProviderRegistry::class, static fn (Container $container) => new ProviderRegistry(
    ['tvmaze' => $container->get(TvMazeShowProvider::class)],
    [
        $container->get(OmdbRatingsProvider::class),
    ]
));
$container->singleton(MailerService::class, static fn (Container $container) => new MailerService(
    $container->get(AppSettingsService::class),
    $container->get(Logger::class)
));
$container->singleton(AuthService::class, static fn (Container $container) => new AuthService(
    $container->get(UserRepository::class),
    $container->get(LoginTokenRepository::class),
    $container->get(AppSettingsService::class),
    $container->get(MailerService::class),
    $container->get(Session::class),
    $container->get('config')
));
$container->singleton(ShowSyncService::class, static fn (Container $container) => new ShowSyncService(
    $container->get(ProviderRegistry::class),
    $container->get(ShowRepository::class),
    $container->get(SeasonRepository::class),
    $container->get(EpisodeRepository::class),
    $container->get(SyncLogRepository::class),
    $container->get(AppSettingsService::class),
    $container->get(\PDO::class)
));
$container->singleton(TrackedShowService::class, static fn (Container $container) => new TrackedShowService(
    $container->get(ShowSyncService::class),
    $container->get(ShowRepository::class),
    $container->get(TrackedShowRepository::class),
    $container->get(ShowUserStateRepository::class)
));
$container->singleton(DashboardService::class, static fn (Container $container) => new DashboardService(
    $container->get(TrackedShowRepository::class),
    $container->get(ShowRepository::class),
    $container->get(EpisodeRepository::class)
));
$container->singleton(SimilarShowsService::class, static fn (Container $container) => new SimilarShowsService(
    $container->get(TMDbClient::class),
    $container->get(AppSettingsService::class),
    $container->get(ShowRepository::class),
    $container->get(Logger::class)
));
$container->singleton(TopListsService::class, static fn (Container $container) => new TopListsService(
    $container->get(TMDbClient::class),
    $container->get(AppSettingsService::class),
    $container->get(Logger::class)
));
$container->singleton(AuthController::class, static fn (Container $container) => new AuthController(
    $container->get(View::class),
    $container->get(AuthService::class),
    $container->get(Csrf::class),
    $container->get(Session::class),
    $container->get(AppSettingsService::class)
));
$container->singleton(DashboardController::class, static fn (Container $container) => new DashboardController(
    $container->get(View::class),
    $container->get(AuthService::class),
    $container->get(DashboardService::class),
    $container->get(UserRepository::class)
));
$container->singleton(ShowController::class, static fn (Container $container) => new ShowController(
    $container->get(View::class),
    $container->get(Csrf::class),
    $container->get(AuthService::class),
    $container->get(ShowSyncService::class),
    $container->get(TrackedShowService::class),
    $container->get(TrackedShowRepository::class),
    $container->get(ShowRepository::class),
    $container->get(EpisodeRepository::class),
    $container->get(SeasonRepository::class),
    $container->get(ShowUserStateRepository::class),
    $container->get(DashboardService::class),
    $container->get(AppSettingsService::class),
    $container->get(SimilarShowsService::class)
));
$container->singleton(SettingsController::class, static fn (Container $container) => new SettingsController(
    $container->get(View::class),
    $container->get(Csrf::class),
    $container->get(AppSettingsService::class),
    $container->get(Session::class),
    $container->get(AuthService::class),
    $container->get(ShowSyncService::class),
    $container->get(TrackedShowRepository::class)
));
$container->singleton(TopController::class, static fn (Container $container) => new TopController(
    $container->get(View::class),
    $container->get(AuthService::class),
    $container->get(TrackedShowRepository::class),
    $container->get(TopListsService::class)
));
$container->singleton(HealthController::class, static fn (Container $container) => new HealthController(
    $container->get(View::class),
    $container->get(\PDO::class),
    $container->get(AppSettingsService::class)
));
$container->singleton(Router::class, static fn (Container $container) => new Router(
    $container->get(View::class),
    $container->get(Logger::class),
    $container->get('config')
));

$container->get(Session::class)->start();

require base_path('routes/web.php');
