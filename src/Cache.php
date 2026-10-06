<?php

declare(strict_types=1);

namespace Ham;

interface Cache
{
    /** Returns null on a miss. */
    public function get(string $key): mixed;

    /** A $ttl of 0 keeps the value until evicted. */
    public function set(string $key, mixed $value, int $ttl = 0): bool;

    public function inc(string $key, int $step = 1): int|false;

    public function dec(string $key, int $step = 1): int|false;
}
