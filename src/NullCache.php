<?php

declare(strict_types=1);

namespace Ham;

/** Stores nothing; the fallback when APCu is unavailable. */
final readonly class NullCache implements Cache
{
    #[\Override]
    public function get(string $key): mixed
    {
        return null;
    }

    #[\Override]
    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        return false;
    }

    #[\Override]
    public function inc(string $key, int $step = 1): int|false
    {
        return false;
    }

    #[\Override]
    public function dec(string $key, int $step = 1): int|false
    {
        return false;
    }
}
