<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Database;

require dirname(__DIR__) . '/bootstrap/autoload.php';

$config = Config::fromEnv(base_path('.env'));
$prefix = (string) $config->get('DB_TABLE_PREFIX', 'seriale_');
$migrationsTable = '`' . str_replace('`', '', $prefix . 'schema_migrations') . '`';
$dbConfig = new Config([
    'DB_DRIVER' => $config->get('DB_DRIVER', 'mysql'),
    'DB_HOST' => $config->get('DB_HOST', '127.0.0.1'),
    'DB_PORT' => $config->get('DB_PORT', '3306'),
    'DB_NAME' => $config->get('DB_NAME', ''),
    'DB_USER' => $config->get('DB_USER', ''),
    'DB_PASS' => $config->get('DB_PASS', ''),
    'DB_CHARSET' => $config->get('DB_CHARSET', 'utf8mb4'),
    'DB_SSLMODE' => $config->get('DB_SSLMODE', 'prefer'),
    'APP_TIMEZONE' => $config->get('APP_TIMEZONE', 'Europe/Warsaw'),
]);

$pdo = (new Database($dbConfig))->connect();
$pdo->exec("CREATE TABLE IF NOT EXISTS {$migrationsTable} (`version` VARCHAR(255) PRIMARY KEY, `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)");

$files = glob(base_path('database/migrations/*.sql')) ?: [];
sort($files);

foreach ($files as $file) {
    $version = basename($file);
    $statement = $pdo->prepare("SELECT COUNT(*) FROM {$migrationsTable} WHERE `version` = :version");
    $statement->execute(['version' => $version]);

    if ((int) $statement->fetchColumn() > 0) {
        echo "Skipping {$version}\n";
        continue;
    }

    echo "Applying {$version}\n";
    $sql = file_get_contents($file);

    if ($sql === false) {
        throw new RuntimeException("Cannot read migration {$version}");
    }

    $sql = str_replace('{{prefix}}', $prefix, $sql);
    $statements = array_values(array_filter(array_map('trim', preg_split('/;\s*\n/', $sql) ?: [])));

    $pdo->beginTransaction();

    try {
        foreach ($statements as $sqlStatement) {
            $pdo->exec($sqlStatement);
        }

        $insert = $pdo->prepare("INSERT INTO {$migrationsTable} (`version`) VALUES (:version)");
        $insert->execute(['version' => $version]);
        $pdo->commit();
    } catch (Throwable $throwable) {
        $pdo->rollBack();
        $message = "Migration failed: {$version}\n" . $throwable->getMessage() . "\n";
        fwrite(STDERR, $message);

        if (PHP_SAPI !== 'cli') {
            echo $message;
        }

        exit(1);
    }
}

syncRuntimeSettings($pdo, $prefix, $config);

echo "Done.\n";

function syncRuntimeSettings(PDO $pdo, string $prefix, Config $config): void
{
    $settingsTable = '`' . str_replace('`', '', $prefix . 'app_settings') . '`';
    $statement = $pdo->prepare(
        "INSERT IGNORE INTO {$settingsTable} (`key`, `value`, `type`, `group_name`) VALUES (:key, :value, :type, :group_name)"
    );

    $items = [
        'app_name' => ['value' => (string) $config->get('APP_NAME', 'Seriale'), 'type' => 'string', 'group_name' => 'general'],
        'app_env' => ['value' => (string) $config->get('APP_ENV', 'production'), 'type' => 'string', 'group_name' => 'general'],
        'app_timezone' => ['value' => (string) $config->get('APP_TIMEZONE', 'Europe/Warsaw'), 'type' => 'string', 'group_name' => 'general'],
        'single_user_identity' => ['value' => (string) $config->get('SINGLE_USER_IDENTITY', 'you@example.com'), 'type' => 'string', 'group_name' => 'general'],
        'cache_ttl_hours' => ['value' => (string) $config->get('CACHE_TTL_HOURS', '12'), 'type' => 'int', 'group_name' => 'sync'],
        'provider_tvmaze_enabled' => ['value' => boolString($config->get('TVMAZE_ENABLED', 'true')), 'type' => 'bool', 'group_name' => 'providers'],
        'provider_tmdb_enabled' => ['value' => boolString($config->get('TMDB_ENABLED', 'false')), 'type' => 'bool', 'group_name' => 'providers'],
        'provider_omdb_enabled' => ['value' => boolString($config->get('OMDB_ENABLED', 'false')), 'type' => 'bool', 'group_name' => 'providers'],
        'tmdb_api_key' => ['value' => (string) $config->get('TMDB_API_KEY', ''), 'type' => 'string', 'group_name' => 'providers'],
        'omdb_api_key' => ['value' => (string) $config->get('OMDB_API_KEY', ''), 'type' => 'string', 'group_name' => 'providers'],
        'mail_transport' => ['value' => (string) $config->get('MAIL_TRANSPORT', 'log'), 'type' => 'string', 'group_name' => 'mail'],
        'mail_from_address' => ['value' => (string) $config->get('MAIL_FROM_ADDRESS', 'no-reply@example.com'), 'type' => 'string', 'group_name' => 'mail'],
        'mail_from_name' => ['value' => (string) $config->get('MAIL_FROM_NAME', 'Seriale'), 'type' => 'string', 'group_name' => 'mail'],
        'cron_secret' => ['value' => (string) $config->get('CRON_SECRET', ''), 'type' => 'string', 'group_name' => 'sync'],
    ];

    foreach ($items as $key => $item) {
        $statement->execute([
            'key' => $key,
            'value' => $item['value'],
            'type' => $item['type'],
            'group_name' => $item['group_name'],
        ]);
    }
}

function boolString(mixed $value): string
{
    if (is_bool($value)) {
        return $value ? '1' : '0';
    }

    return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true) ? '1' : '0';
}
