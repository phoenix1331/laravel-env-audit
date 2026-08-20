<?php

namespace Phoenix1331\LaravelEnvAudit\Data;

class PossibleSecret
{
    public function __construct(
        public readonly string $key,
        public readonly string $maskedValue,
        public readonly string $reason, // 'pattern' or 'entropy'
        public readonly string $detail, // human-readable explanation
    ) {}
}
