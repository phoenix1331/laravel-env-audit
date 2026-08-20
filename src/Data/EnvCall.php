<?php

namespace Phoenix1331\LaravelEnvAudit\Data;

class EnvCall
{
    public function __construct(
        public readonly string $file,
        public readonly int $line,
        public readonly ?string $key,
        public readonly bool $inConfig,
    ) {}
}
