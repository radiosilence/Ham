<?php

declare(strict_types=1);

namespace Ham;

interface Logger
{
    public function error(string $message): void;

    public function info(string $message): void;

    public function log(string $message): void;
}
