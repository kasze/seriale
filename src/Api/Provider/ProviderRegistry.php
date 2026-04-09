<?php

declare(strict_types=1);

namespace App\Api\Provider;

use RuntimeException;

final class ProviderRegistry
{
    /**
     * @param array<string, ShowProviderInterface> $showProviders
     * @param RatingsProviderInterface[] $ratingsProviders
     */
    public function __construct(
        private array $showProviders,
        private array $ratingsProviders
    ) {
    }

    public function showProvider(string $name): ShowProviderInterface
    {
        if (!isset($this->showProviders[$name])) {
            throw new RuntimeException("Provider {$name} is not registered.");
        }

        return $this->showProviders[$name];
    }

    public function ratingsProviders(): array
    {
        return array_values(array_filter($this->ratingsProviders, static fn (RatingsProviderInterface $provider) => $provider->enabled()));
    }
}

