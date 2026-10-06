<?php

declare(strict_types=1);

namespace Ham;

/** Appends tab-separated `timestamp, severity, message` lines to a file. */
final readonly class FileLogger implements Logger
{
    public function __construct(private string $path)
    {
        if (file_exists($path) ? !is_writable($path) : !is_writable(dirname($path))) {
            throw new \RuntimeException("Log file is not writable: {$path}");
        }
    }

    #[\Override]
    public function error(string $message): void
    {
        $this->write('error', $message);
    }

    #[\Override]
    public function info(string $message): void
    {
        $this->write('info', $message);
    }

    #[\Override]
    public function log(string $message): void
    {
        $this->write('log', $message);
    }

    private function write(string $severity, string $message): void
    {
        file_put_contents($this->path, sprintf("%s\t%s\t%s\n", date('Y-m-d H:i:s'), $severity, $message), FILE_APPEND | LOCK_EX);
    }
}
