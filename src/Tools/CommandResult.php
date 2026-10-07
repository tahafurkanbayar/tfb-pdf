<?php

declare(strict_types=1);

namespace App\Tools;

final class CommandResult
{
    public function __construct(
        public readonly int $exitCode,
        public readonly string $stdout,
        public readonly string $stderr,
        public readonly bool $timedOut,
        public readonly int $durationMs,
    ) {
    }

    public function successful(): bool
    {
        return !$this->timedOut && $this->exitCode === 0;
    }
}
