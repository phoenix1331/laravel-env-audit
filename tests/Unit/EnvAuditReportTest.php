<?php

use Phoenix1331\LaravelEnvAudit\Data\EnvAuditReport;
use Phoenix1331\LaravelEnvAudit\Data\EnvCall;
use Phoenix1331\LaravelEnvAudit\Data\IgnoreEntry;
use Phoenix1331\LaravelEnvAudit\Data\PossibleSecret;

function makeCall(bool $inConfig, string $key = 'APP_NAME'): EnvCall
{
    return new EnvCall(file: '/app/foo.php', line: 1, key: $key, inConfig: $inConfig);
}

function makeIgnore(bool $expired): IgnoreEntry
{
    return new IgnoreEntry(
        file: '/app/foo.php',
        line: 1,
        reason: 'test reason',
        expires: $expired ? '2020-01-01' : null,
        expired: $expired,
        source: 'attribute',
    );
}

function makeSecret(): PossibleSecret
{
    return new PossibleSecret(
        key: 'STRIPE_SECRET',
        maskedValue: 'sk_l****',
        reason: 'pattern',
        detail: 'Stripe live key',
    );
}

// isolationScore

it('returns 100 when there are no env() calls', function () {
    $report = new EnvAuditReport([], [], [], [], [], [], 0, 0);
    expect($report->isolationScore())->toBe(100);
});

it('returns 100 when all calls are in config', function () {
    $report = new EnvAuditReport([], [], [], [], [], [], 5, 5);
    expect($report->isolationScore())->toBe(100);
});

it('returns 0 when no calls are in config', function () {
    $report = new EnvAuditReport([], [], [], [], [], [], 4, 0);
    expect($report->isolationScore())->toBe(0);
});

it('rounds the isolation score correctly', function () {
    // 17 of 18 calls in config = 94.44% -> rounds to 94
    $report = new EnvAuditReport([], [], [], [], [], [], 18, 17);
    expect($report->isolationScore())->toBe(94);
});

// countFor

it('counts direct usage violations', function () {
    $report = new EnvAuditReport([makeCall(false), makeCall(false)], [], [], [], [], [], 2, 0);
    expect($report->countFor('direct-usage'))->toBe(2);
});

it('counts possible secrets', function () {
    $report = new EnvAuditReport([], [], [], [makeSecret()], [], [], 0, 0);
    expect($report->countFor('possible-secret'))->toBe(1);
});

it('counts missing from example', function () {
    $report = new EnvAuditReport([], ['FEATURE_FLAG'], [], [], [], [], 0, 0);
    expect($report->countFor('missing-from-example'))->toBe(1);
});

it('counts unused in example', function () {
    $report = new EnvAuditReport([], [], ['OLD_KEY'], [], [], [], 0, 0);
    expect($report->countFor('unused-in-example'))->toBe(1);
});

it('returns 0 for unknown category', function () {
    $report = new EnvAuditReport([], [], [], [], [], [], 0, 0);
    expect($report->countFor('nonexistent-category'))->toBe(0);
});

// hasViolationsIn

it('returns true when any specified category has violations', function () {
    $report = new EnvAuditReport([makeCall(false)], [], [], [], [], [], 1, 0);
    expect($report->hasViolationsIn(['direct-usage', 'possible-secret']))->toBeTrue();
});

it('returns false when no specified category has violations', function () {
    $report = new EnvAuditReport([], [], [], [], [], [], 5, 5);
    expect($report->hasViolationsIn(['direct-usage', 'possible-secret']))->toBeFalse();
});

// build()

it('builds correctly from raw scanner outputs', function () {
    $allCalls = [makeCall(true), makeCall(true), makeCall(false)];
    $directViolations = [makeCall(false)];
    $ignores = [makeIgnore(false), makeIgnore(true)];

    $report = EnvAuditReport::build(
        allCalls: $allCalls,
        directViolations: $directViolations,
        missingFromExample: ['FEATURE_FLAG'],
        unusedInExample: ['OLD_KEY'],
        possibleSecrets: [makeSecret()],
        allIgnores: $ignores,
    );

    expect($report->totalEnvCalls)->toBe(3);
    expect($report->configEnvCalls)->toBe(2);
    expect($report->isolationScore())->toBe(67);
    expect($report->directUsageViolations)->toHaveCount(1);
    expect($report->missingFromExample)->toBe(['FEATURE_FLAG']);
    expect($report->unusedInExample)->toBe(['OLD_KEY']);
    expect($report->possibleSecrets)->toHaveCount(1);
    expect($report->ignores)->toHaveCount(1);       // active only
    expect($report->expiredIgnores)->toHaveCount(1); // expired separated out
});
