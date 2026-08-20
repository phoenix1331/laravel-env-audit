<?php

namespace Phoenix1331\LaravelEnvAudit\Data;

class EnvAuditReport
{
    /**
     * @param  array<EnvCall>  $directUsageViolations  env() calls outside config/
     * @param  array<string>  $missingFromExample  keys used in config but absent from .env.example
     * @param  array<string>  $unusedInExample  keys in .env.example never referenced anywhere
     * @param  array<PossibleSecret>  $possibleSecrets  flagged .env.example values
     * @param  array<IgnoreEntry>  $ignores  all active bypass entries
     * @param  array<IgnoreEntry>  $expiredIgnores  bypass entries whose expires date has passed
     * @param  array<IgnoreEntry>  $missingReasonIgnores  bypass entries with no documented reason
     * @param  int  $totalEnvCalls  total env() calls scanned
     * @param  int  $configEnvCalls  env() calls inside config/
     * @param  int  $skippedFiles  files that could not be parsed
     */
    public function __construct(
        public readonly array $directUsageViolations,
        public readonly array $missingFromExample,
        public readonly array $unusedInExample,
        public readonly array $possibleSecrets,
        public readonly array $ignores,
        public readonly array $expiredIgnores,
        public readonly array $missingReasonIgnores,
        public readonly int $totalEnvCalls,
        public readonly int $configEnvCalls,
        public readonly int $skippedFiles,
    ) {}

    /**
     * Isolation score: percentage of env() calls that live inside config/.
     * Returns 100 when there are no env() calls at all.
     */
    public function isolationScore(): int
    {
        if ($this->totalEnvCalls === 0) {
            return 100;
        }

        return (int) round(($this->configEnvCalls / $this->totalEnvCalls) * 100);
    }

    /**
     * Whether the report has any findings in the given category.
     *
     * @param  array<string>  $categories  one or more of: direct-usage, possible-secret,
     *                                     missing-from-example, unused-in-example
     */
    public function hasViolationsIn(array $categories): bool
    {
        foreach ($categories as $category) {
            if ($this->countFor($category) > 0) {
                return true;
            }
        }

        return false;
    }

    public function countFor(string $category): int
    {
        return match ($category) {
            'direct-usage' => count($this->directUsageViolations),
            'possible-secret' => count($this->possibleSecrets),
            'missing-from-example' => count($this->missingFromExample),
            'unused-in-example' => count($this->unusedInExample),
            'missing-reason' => count($this->missingReasonIgnores),
            default => 0,
        };
    }

    /**
     * Build an EnvAuditReport from the raw scanner outputs.
     *
     * @param  array<EnvCall>  $allCalls
     * @param  array<EnvCall>  $directViolations  calls outside config/ not covered by an ignore
     * @param  array<string>  $missingFromExample
     * @param  array<string>  $unusedInExample
     * @param  array<PossibleSecret>  $possibleSecrets
     * @param  array<IgnoreEntry>  $allIgnores
     */
    public static function build(
        array $allCalls,
        array $directViolations,
        array $missingFromExample,
        array $unusedInExample,
        array $possibleSecrets,
        array $allIgnores,
        int $skippedFiles = 0,
        bool $requireReasons = false,
    ): self {
        $totalEnvCalls = count($allCalls);
        $configEnvCalls = count(array_filter($allCalls, fn (EnvCall $c) => $c->inConfig));
        $expiredIgnores = array_values(array_filter($allIgnores, fn (IgnoreEntry $i) => $i->expired));
        $activeIgnores = array_values(array_filter($allIgnores, fn (IgnoreEntry $i) => ! $i->expired));
        $missingReasonIgnores = $requireReasons
            ? array_values(array_filter($activeIgnores, fn (IgnoreEntry $i) => trim($i->reason) === ''))
            : [];

        return new self(
            directUsageViolations: $directViolations,
            missingFromExample: $missingFromExample,
            unusedInExample: $unusedInExample,
            possibleSecrets: $possibleSecrets,
            ignores: $activeIgnores,
            expiredIgnores: $expiredIgnores,
            missingReasonIgnores: $missingReasonIgnores,
            totalEnvCalls: $totalEnvCalls,
            configEnvCalls: $configEnvCalls,
            skippedFiles: $skippedFiles,
        );
    }
}
