<?php

namespace Phoenix1331\LaravelEnvAudit\Scanning;

use Phoenix1331\LaravelEnvAudit\Data\PossibleSecret;

class SecretHeuristicDetector
{
    /**
     * Known credential prefixes — these are real provider-issued key shapes.
     * Values are human-readable descriptions for the report.
     *
     * @var array<string, string>
     */
    private const BUILTIN_PATTERNS = [
        '/^sk_live_[A-Za-z0-9]{10,}/' => 'Stripe live secret key (sk_live_...)',
        '/^sk_test_[A-Za-z0-9]{10,}/' => 'Stripe test secret key (sk_test_...)',
        '/^pk_live_[A-Za-z0-9]{10,}/' => 'Stripe live publishable key (pk_live_...)',
        '/^pk_test_[A-Za-z0-9]{10,}/' => 'Stripe test publishable key (pk_test_...)',
        '/^rk_live_[A-Za-z0-9]{10,}/' => 'Stripe restricted key (rk_live_...)',
        '/^AKIA[A-Z0-9]{16}$/' => 'AWS access key ID (AKIA...)',
        '/^ghp_[A-Za-z0-9]{36}/' => 'GitHub personal access token (ghp_...)',
        '/^ghs_[A-Za-z0-9]{36}/' => 'GitHub Actions token (ghs_...)',
        '/^ghr_[A-Za-z0-9]{36}/' => 'GitHub refresh token (ghr_...)',
        '/^xox[baprs]-[0-9A-Za-z\-]{10,}/' => 'Slack token (xox...)',
        '/^EAA[A-Za-z0-9]{20,}/' => 'Facebook access token (EAA...)',
        '/^AIza[A-Za-z0-9_\-]{35}/' => 'Google API key (AIza...)',
        '/^SG\.[A-Za-z0-9\-_]{22}\.[A-Za-z0-9\-_]{43}/' => 'SendGrid API key (SG....)',
        '/^AC[a-z0-9]{32}$/' => 'Twilio Account SID (AC...)',
        '/^[a-f0-9]{32}$/' => 'Looks like a raw hex secret (32-char hex)',
    ];

    /** Minimum length for entropy check — short values are likely placeholders */
    private const ENTROPY_MIN_LENGTH = 20;

    /** Shannon entropy threshold — real secrets are typically above 3.5 bits/char */
    private const ENTROPY_THRESHOLD = 3.5;

    /** Values that clearly look like placeholders and should not trigger entropy */
    private const PLACEHOLDER_PATTERNS = [
        '/^your[-_]/i',
        '/^change[-_]?me/i',
        '/^replace[-_]?me/i',
        '/^example/i',
        '/^placeholder/i',
        '/^insert[-_]/i',
        '/^<.+>$/',
        '/^\{.+\}$/',
        '/^xxx/i',
        '/^null$/i',
        '/^false$/i',
        '/^true$/i',
        '/^\*+$/',
    ];

    /** @param array<string> $extraPatterns Additional regex patterns from config */
    public function __construct(private readonly array $extraPatterns = []) {}

    /**
     * Scan a key=>value map from .env.example and return any suspected secrets.
     * Real .env values must NEVER be passed to this method.
     *
     * @param  array<string, string>  $exampleEntries
     * @return array<PossibleSecret>
     */
    public function detect(array $exampleEntries): array
    {
        $found = [];

        foreach ($exampleEntries as $key => $value) {
            if ($value === '' || $this->isPlaceholder($value)) {
                continue;
            }

            if ($hit = $this->matchesPattern($key, $value)) {
                $found[] = $hit;

                continue;
            }

            if ($hit = $this->checkEntropy($key, $value)) {
                $found[] = $hit;
            }
        }

        return $found;
    }

    private function matchesPattern(string $key, string $value): ?PossibleSecret
    {
        $patterns = array_merge(array_keys(self::BUILTIN_PATTERNS), $this->extraPatterns);

        foreach ($patterns as $pattern) {
            if (! preg_match($pattern, $value)) {
                continue;
            }

            $description = self::BUILTIN_PATTERNS[$pattern] ?? 'matches custom pattern: '.$pattern;

            return new PossibleSecret(
                key: $key,
                maskedValue: $this->mask($value),
                reason: 'pattern',
                detail: $description,
            );
        }

        return null;
    }

    private function checkEntropy(string $key, string $value): ?PossibleSecret
    {
        if (strlen($value) < self::ENTROPY_MIN_LENGTH) {
            return null;
        }

        $entropy = $this->shannonEntropy($value);

        if ($entropy < self::ENTROPY_THRESHOLD) {
            return null;
        }

        return new PossibleSecret(
            key: $key,
            maskedValue: $this->mask($value),
            reason: 'entropy',
            detail: sprintf('high entropy value (%.2f bits/char) — may be a real secret', $entropy),
        );
    }

    private function isPlaceholder(string $value): bool
    {
        foreach (self::PLACEHOLDER_PATTERNS as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Mask a secret value for safe display in reports.
     * The first 4 characters are shown, the rest replaced with asterisks.
     * This preserves enough to identify the key type while hiding the secret.
     */
    public function mask(string $value): string
    {
        $len = strlen($value);

        if ($len <= 4) {
            return str_repeat('*', $len);
        }

        return substr($value, 0, 4).str_repeat('*', min($len - 4, 24));
    }

    /**
     * Calculate Shannon entropy in bits per character.
     */
    private function shannonEntropy(string $value): float
    {
        $len = strlen($value);

        if ($len === 0) {
            return 0.0;
        }

        $frequencies = array_count_values(str_split($value));
        $entropy = 0.0;

        foreach ($frequencies as $count) {
            $probability = $count / $len;
            $entropy -= $probability * log($probability, 2);
        }

        return $entropy;
    }
}
