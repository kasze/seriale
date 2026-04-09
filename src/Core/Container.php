<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Container
{
    private array $bindings = [];

    private array $instances = [];

    public function set(string $id, mixed $concrete): void
    {
        $this->bindings[$id] = $concrete;
    }

    public function singleton(string $id, callable $factory): void
    {
        $this->bindings[$id] = $factory;
    }

    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        if (!array_key_exists($id, $this->bindings)) {
            throw new RuntimeException("Container binding [{$id}] not found.");
        }

        $concrete = $this->bindings[$id];
        $value = is_callable($concrete) ? $concrete($this) : $concrete;

        if (is_callable($concrete)) {
            $this->instances[$id] = $value;
        }

        return $value;
    }
}

