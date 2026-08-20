<?php

use Phoenix1331\LaravelEnvAudit\Scanning\SecretHeuristicDetector;

beforeEach(function () {
    $this->detector = new SecretHeuristicDetector;
});

// Pattern detection

it('flags a stripe live secret key', function () {
    // Built programmatically so the literal never appears in source
    $value = implode('', ['sk', '_', 'live', '_', 'abcdefghij1234567890']);
    $results = $this->detector->detect(['STRIPE_SECRET' => $value]);

    expect($results)->toHaveCount(1);
    expect($results[0]->key)->toBe('STRIPE_SECRET');
    expect($results[0]->reason)->toBe('pattern');
    expect($results[0]->detail)->toContain('Stripe live secret key');
});

it('flags a stripe test secret key', function () {
    $value = implode('', ['sk', '_', 'test', '_', 'abcdefghij1234567890']);
    $results = $this->detector->detect(['STRIPE_SECRET' => $value]);

    expect($results)->toHaveCount(1);
    expect($results[0]->reason)->toBe('pattern');
});

it('flags an aws access key id', function () {
    $results = $this->detector->detect(['AWS_KEY' => 'AKIAIOSFODNN7EXAMPLE']);

    expect($results)->toHaveCount(1);
    expect($results[0]->detail)->toContain('AWS access key ID');
});

it('flags a github personal access token', function () {
    $results = $this->detector->detect(['GH_TOKEN' => 'ghp_'.str_repeat('A', 36)]);

    expect($results)->toHaveCount(1);
    expect($results[0]->detail)->toContain('GitHub personal access token');
});

it('flags a slack token', function () {
    $results = $this->detector->detect(['SLACK_TOKEN' => 'xoxb-1234567890-abcdefghij']);

    expect($results)->toHaveCount(1);
    expect($results[0]->detail)->toContain('Slack token');
});

// Placeholder detection

it('does not flag a placeholder value starting with your-', function () {
    $results = $this->detector->detect(['STRIPE_SECRET' => 'your-stripe-secret-here']);

    expect($results)->toHaveCount(0);
});

it('does not flag change-me values', function () {
    $results = $this->detector->detect(['APP_KEY' => 'change-me']);

    expect($results)->toHaveCount(0);
});

it('does not flag angle-bracket placeholders', function () {
    $results = $this->detector->detect(['DB_PASSWORD' => '<your-password>']);

    expect($results)->toHaveCount(0);
});

it('does not flag empty values', function () {
    $results = $this->detector->detect(['SECRET' => '']);

    expect($results)->toHaveCount(0);
});

it('does not flag null string', function () {
    $results = $this->detector->detect(['CACHE_DRIVER' => 'null']);

    expect($results)->toHaveCount(0);
});

// Entropy detection

it('flags a high-entropy value long enough to trigger the check', function () {
    // 40-char random-looking alphanumeric — high entropy, not a known pattern
    $value = 'aB3xQ9zR2mK7wP1nY6vD4cF8gH5jL0eT';
    $results = $this->detector->detect(['SOME_TOKEN' => $value]);

    expect($results)->toHaveCount(1);
    expect($results[0]->reason)->toBe('entropy');
});

it('does not flag short high-entropy values', function () {
    $results = $this->detector->detect(['SHORT' => 'aB3xQ9z']);

    expect($results)->toHaveCount(0);
});

it('does not flag low-entropy long values', function () {
    // Repeated characters have very low entropy
    $results = $this->detector->detect(['BORING' => str_repeat('aaaa', 10)]);

    expect($results)->toHaveCount(0);
});

// Masking — the critical safety requirement

it('never exposes the full value in maskedValue', function () {
    $value = implode('', ['sk', '_', 'live', '_', 'realSecretKeyThatMustNeverLeak12345']);
    $results = $this->detector->detect(['STRIPE_SECRET' => $value]);

    expect($results)->toHaveCount(1);
    expect($results[0]->maskedValue)->not->toBe($value);
    expect($results[0]->maskedValue)->toContain('*');
});

it('masks preserving only the first 4 characters', function () {
    $value = implode('', ['sk', '_', 'live', '_', 'abcdefghij1234567890']);
    $results = $this->detector->detect(['STRIPE_SECRET' => $value]);

    expect($results[0]->maskedValue)->toStartWith('sk_l');
    expect($results[0]->maskedValue)->toContain('****');
});

it('real value never appears in any field of the result', function () {
    $realValue = implode('', ['sk', '_', 'live', '_', 'SuperRealKeyNeverReveal9999']);
    $results = $this->detector->detect(['STRIPE_SECRET' => $realValue]);

    expect($results)->toHaveCount(1);
    $result = $results[0];

    // Check every string field — none must contain the full real value
    expect($result->maskedValue)->not->toContain($realValue);
    expect($result->detail)->not->toContain($realValue);
    expect($result->reason)->not->toContain($realValue);
    expect($result->key)->not->toContain($realValue);
});

// Extra patterns

it('respects extra patterns from config', function () {
    $detector = new SecretHeuristicDetector(['/^my_custom_prefix_/']);
    $results = $detector->detect(['CUSTOM_KEY' => 'my_custom_prefix_secretvalue']);

    expect($results)->toHaveCount(1);
    expect($results[0]->reason)->toBe('pattern');
});

// Multiple entries

it('returns one finding per flagged key', function () {
    $entries = [
        'STRIPE_SECRET' => implode('', ['sk', '_', 'live', '_', 'abcdefghij1234567890']),
        'SAFE_KEY' => 'your-value-here',
        'GH_TOKEN' => 'ghp_'.str_repeat('B', 36),
    ];

    $results = $this->detector->detect($entries);

    expect($results)->toHaveCount(2);
});
