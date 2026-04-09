<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class HttpClient
{
    public function __construct(private Logger $logger)
    {
    }

    public function getJson(string $url, array $headers = [], int $timeout = 10): array
    {
        $response = $this->get($url, $headers, $timeout);
        $decoded = json_decode($response, true);

        if (!is_array($decoded)) {
            throw new RuntimeException("Nieprawidlowa odpowiedz JSON z {$url}");
        }

        return $decoded;
    }

    public function get(string $url, array $headers = [], int $timeout = 10): string
    {
        $headers = array_merge([
            'Accept: application/json',
            'User-Agent: Seriale/1.0 (+shared-hosting-app)',
        ], $headers);

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($body === false || $status >= 400) {
                $this->logger->warning('HTTP request failed', ['url' => $url, 'status' => $status, 'error' => $error]);
                throw new RuntimeException("HTTP {$status} for {$url}");
            }

            return (string) $body;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $timeout,
                'header' => implode("\r\n", $headers),
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
        $statusLine = $http_response_header[0] ?? 'HTTP/1.1 500';
        preg_match('/\s(\d{3})\s/', $statusLine, $matches);
        $status = (int) ($matches[1] ?? 500);

        if ($body === false || $status >= 400) {
            $this->logger->warning('HTTP request failed', ['url' => $url, 'status' => $status]);
            throw new RuntimeException("HTTP {$status} for {$url}");
        }

        return (string) $body;
    }
}

