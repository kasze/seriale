<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    public function __construct(
        private string $content = '',
        private int $status = 200,
        private array $headers = []
    ) {
    }

    public static function html(string $content, int $status = 200, array $headers = []): self
    {
        return new self($content, $status, array_merge(['Content-Type' => 'text/html; charset=UTF-8'], $headers));
    }

    public static function json(array $payload, int $status = 200, array $headers = []): self
    {
        return new self(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}', $status, array_merge(['Content-Type' => 'application/json; charset=UTF-8'], $headers));
    }

    public static function redirect(string $location, int $status = 302): self
    {
        if (
            $location !== ''
            && $location[0] === '/'
            && !preg_match('#^//|https?://#i', $location)
            && function_exists('app')
        ) {
            try {
                $basePath = rtrim((string) app('config')->get('app.base_path', ''), '/');
                $location = ($basePath === '' ? '' : $basePath) . $location;
            } catch (\Throwable) {
            }
        }

        return new self('', $status, ['Location' => $location]);
    }

    public function withHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;

        return $clone;
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        echo $this->content;
    }
}
