<?php

declare(strict_types=1);

namespace Ham;

final readonly class ApcuCache implements Cache
{
    public function __construct(private string $prefix = '') {}

    #[\Override]
    public function get(string $key): mixed
    {
        $value = apcu_fetch($this->prefix . $key, $found);

        return $found ? $value : null;
    }

    #[\Override]
    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        return apcu_store($this->prefix . $key, $value, $ttl);
    }

    #[\Override]
    public function inc(string $key, int $step = 1): int|false
    {
        return apcu_inc($this->prefix . $key, $step);
    }

    #[\Override]
    public function dec(string $key, int $step = 1): int|false
    {
        return apcu_dec($this->prefix . $key, $step);
    }
}
