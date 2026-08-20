<?php

namespace Phoenix1331\LaravelEnvAudit\Data;

class IgnoreEntry
{
    public function __construct(
        public readonly string $file,
        public readonly int $line,
        public readonly string $reason,
        public readonly ?string $expires,
        public readonly bool $expired,
        public readonly string $source, // 'attribute' or 'inline-comment'
    ) {}
}
