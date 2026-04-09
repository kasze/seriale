<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Csrf
{
    public function __construct(private Session $session)
    {
    }

    public function token(): string
    {
        $token = $this->session->get('_csrf_token');

        if (is_string($token) && $token !== '') {
            return $token;
        }

        $token = bin2hex(random_bytes(32));
        $this->session->put('_csrf_token', $token);

        return $token;
    }

    public function validate(?string $token): void
    {
        if (!is_string($token) || !hash_equals($this->token(), $token)) {
            throw new RuntimeException('Nieprawidlowy token CSRF.');
        }
    }
}

