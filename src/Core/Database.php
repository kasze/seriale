<?php

declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class Database
{
    public function __construct(private Config $config)
    {
    }

    public function connect(): PDO
    {
        $driver = (string) $this->config->get('DB_DRIVER', 'mysql');
        $host = $this->config->get('DB_HOST', '127.0.0.1');
        $port = $this->config->get('DB_PORT', $driver === 'pgsql' ? '5432' : '3306');
        $name = $this->config->get('DB_NAME', '');
        $charset = (string) $this->config->get('DB_CHARSET', 'utf8mb4');
        $timezone = (string) $this->config->get('APP_TIMEZONE', 'UTC');

        $dsn = $driver === 'pgsql'
            ? sprintf(
                'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
                $host,
                $port,
                $name,
                (string) $this->config->get('DB_SSLMODE', 'prefer')
            )
            : sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $name, $charset);

        $pdo = new PDO($dsn, (string) $this->config->get('DB_USER', ''), (string) $this->config->get('DB_PASS', ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        if ($driver === 'pgsql') {
            $pdo->exec("SET TIME ZONE '" . str_replace("'", "''", $timezone) . "'");
        } else {
            $offset = (new DateTimeImmutable('now', new DateTimeZone($timezone)))->format('P');
            $pdo->exec("SET time_zone = '" . str_replace("'", "''", $offset) . "'");
        }

        return $pdo;
    }
}
