<?php

namespace Phoenix1331\LaravelEnvAudit\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION)]
class WithoutEnvAudit
{
    public function __construct(
        public readonly string $reason,
        public readonly ?string $expires = null,
    ) {}
}
