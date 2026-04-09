<?php

declare(strict_types=1);

namespace App\Api\Provider;

interface ShowProviderInterface
{
    public function name(): string;

    public function search(string $query): array;

    public function fetchShow(string $sourceId): array;
}

