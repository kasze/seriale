<?php

declare(strict_types=1);

namespace App\Api\Provider;

interface RatingsProviderInterface
{
    public function name(): string;

    public function enabled(): bool;

    public function enrich(array $show): array;
}

