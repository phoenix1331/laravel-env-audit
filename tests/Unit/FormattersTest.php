<?php

use Phoenix1331\LaravelEnvAudit\Data\EnvAuditReport;
use Phoenix1331\LaravelEnvAudit\Data\EnvCall;
use Phoenix1331\LaravelEnvAudit\Data\IgnoreEntry;
use Phoenix1331\LaravelEnvAudit\Data\PossibleSecret;
use Phoenix1331\LaravelEnvAudit\Formatters\HtmlFormatter;
use Phoenix1331\LaravelEnvAudit\Formatters\JsonFormatter;

// Helpers

function buildReport(array $overrides = []): EnvAuditReport
{
    return new EnvAuditReport(
        directUsageViolations: $overrides['direct'] ?? [],
        missingFromExample: $overrides['missing'] ?? [],
        unusedInExample: $overrides['unused'] ?? [],
        possibleSecrets: $overrides['secrets'] ?? [],
        ignores: $overrides['ignores'] ?? [],
        expiredIgnores: $overrides['expired'] ?? [],
        totalEnvCalls: $overrides['total'] ?? 0,
        configEnvCalls: $overrides['config'] ?? 0,
    );
}

function directCall(string $key = 'APP_NAME', string $file = '/app/Service.php', int $line = 10): EnvCall
{
    return new EnvCall(file: $file, line: $line, key: $key, inConfig: false);
}

function secret(string $key = 'STRIPE_SECRET', string $masked = 'sk_l****'): PossibleSecret
{
    return new PossibleSecret(key: $key, maskedValue: $masked, reason: 'pattern', detail: 'Stripe live key');
}

function ignore(bool $expired = false): IgnoreEntry
{
    return new IgnoreEntry(
        file: '/app/Provider.php',
        line: 5,
        reason: 'legacy workaround',
        expires: $expired ? '2020-01-01' : '2099-01-01',
        expired: $expired,
        source: 'attribute',
    );
}

// --- JsonFormatter ---

it('json output contains isolation score', function () {
    $report = buildReport(['total' => 10, 'config' => 9]);
    $json = json_decode((new JsonFormatter)->format($report), true);

    expect($json['isolation_score'])->toBe(90);
    expect($json['total_env_calls'])->toBe(10);
    expect($json['config_env_calls'])->toBe(9);
});

it('json output contains direct usage violations', function () {
    $report = buildReport(['direct' => [directCall('DB_HOST', '/app/Service.php', 42)]]);
    $json = json_decode((new JsonFormatter)->format($report), true);

    expect($json['direct_usage'])->toHaveCount(1);
    expect($json['direct_usage'][0]['key'])->toBe('DB_HOST');
    expect($json['direct_usage'][0]['line'])->toBe(42);
});

it('json output contains masked secret value not the real one', function () {
    $report = buildReport(['secrets' => [secret('STRIPE_SECRET', 'sk_l****')]]);
    $json = json_decode((new JsonFormatter)->format($report), true);

    expect($json['possible_secrets'][0]['masked_value'])->toBe('sk_l****');
    expect($json['possible_secrets'][0])->not->toHaveKey('value');
});

it('json output never contains real secret values', function () {
    $realValue = 'sk_live_realKeyThatMustNeverAppear99';
    // The masked value is what the detector returns; we verify it is masked
    $maskedValue = 'sk_l'.str_repeat('*', 24);
    $report = buildReport(['secrets' => [new PossibleSecret('STRIPE_SECRET', $maskedValue, 'pattern', 'Stripe live')]]);

    $json = (new JsonFormatter)->format($report);

    expect($json)->not->toContain($realValue);
});

it('json output contains missing and unused keys', function () {
    $report = buildReport(['missing' => ['FEATURE_FLAG'], 'unused' => ['OLD_KEY']]);
    $json = json_decode((new JsonFormatter)->format($report), true);

    expect($json['missing_from_example'])->toContain('FEATURE_FLAG');
    expect($json['unused_in_example'])->toContain('OLD_KEY');
});

it('json output separates active and expired bypasses', function () {
    $report = buildReport(['ignores' => [ignore(false)], 'expired' => [ignore(true)]]);
    $json = json_decode((new JsonFormatter)->format($report), true);

    expect($json['bypasses']['active'])->toHaveCount(1);
    expect($json['bypasses']['expired'])->toHaveCount(1);
});

it('json output is valid json', function () {
    $report = buildReport();
    $json = (new JsonFormatter)->format($report);

    expect(json_decode($json))->not->toBeNull();
});

// --- HtmlFormatter ---

it('html output contains the isolation score', function () {
    $report = buildReport(['total' => 18, 'config' => 17]);
    $html = (new HtmlFormatter)->format($report);

    expect($html)->toContain('94%');
});

it('html output contains direct usage file and key', function () {
    $report = buildReport(['direct' => [directCall('APP_KEY', '/app/LegacyService.php', 12)]]);
    $html = (new HtmlFormatter)->format($report);

    expect($html)->toContain('/app/LegacyService.php');
    expect($html)->toContain('APP_KEY');
});

it('html output never contains a real secret value', function () {
    $realValue = 'sk_live_thisMustNeverAppearInHtml999';
    $masked = 'sk_l'.str_repeat('*', 24);
    $report = buildReport(['secrets' => [new PossibleSecret('STRIPE_SECRET', $masked, 'pattern', 'Stripe live')]]);

    $html = (new HtmlFormatter)->format($report);

    expect($html)->not->toContain($realValue);
    expect($html)->toContain($masked);
});

it('html output contains masked value for secrets', function () {
    $report = buildReport(['secrets' => [secret('STRIPE_SECRET', 'sk_l****')]]);
    $html = (new HtmlFormatter)->format($report);

    expect($html)->toContain('sk_l****');
    expect($html)->toContain('STRIPE_SECRET');
});

it('html output contains missing and unused key names', function () {
    $report = buildReport(['missing' => ['FEATURE_NEW_CHECKOUT'], 'unused' => ['OLD_PAYMENT_PROVIDER_KEY']]);
    $html = (new HtmlFormatter)->format($report);

    expect($html)->toContain('FEATURE_NEW_CHECKOUT');
    expect($html)->toContain('OLD_PAYMENT_PROVIDER_KEY');
});

it('html output shows active bypass reason', function () {
    $report = buildReport(['ignores' => [ignore(false)]]);
    $html = (new HtmlFormatter)->format($report);

    expect($html)->toContain('legacy workaround');
});

it('html output shows expired bypass section when expired ignores exist', function () {
    $report = buildReport(['expired' => [ignore(true)]]);
    $html = (new HtmlFormatter)->format($report);

    expect($html)->toContain('Expired Bypasses');
    expect($html)->toContain('2020-01-01');
});

it('html output does not show expired bypass section when none exist', function () {
    $report = buildReport();
    $html = (new HtmlFormatter)->format($report);

    expect($html)->not->toContain('Expired Bypasses');
});

it('html output is a complete document with doctype', function () {
    $html = (new HtmlFormatter)->format(buildReport());

    expect($html)->toContain('<!DOCTYPE html>');
    expect($html)->toContain('</html>');
});

it('html output escapes special characters in file paths', function () {
    $report = buildReport(['direct' => [directCall('KEY', '/app/Service<Fake>.php', 1)]]);
    $html = (new HtmlFormatter)->format($report);

    expect($html)->toContain('&lt;Fake&gt;');
    expect($html)->not->toContain('<Fake>');
});

it('html output uses the custom title', function () {
    $html = (new HtmlFormatter('My Custom Report'))->format(buildReport());

    expect($html)->toContain('My Custom Report');
});
