<?php

use Phoenix1331\LaravelEnvAudit\Data\EnvCall;
use Phoenix1331\LaravelEnvAudit\Scanning\ConfigUsageResolver;

beforeEach(function () {
    $this->resolver = new ConfigUsageResolver;
});

function call(string $key, bool $inConfig): EnvCall
{
    return new EnvCall(file: '/app/foo.php', line: 1, key: $key, inConfig: $inConfig);
}

// keysUsedInConfig

it('returns keys from env() calls inside config files', function () {
    $calls = [
        call('APP_NAME', true),
        call('DB_HOST', true),
    ];

    expect($this->resolver->keysUsedInConfig($calls))->toBe(['APP_NAME', 'DB_HOST']);
});

it('excludes calls outside config', function () {
    $calls = [
        call('APP_NAME', true),
        call('SECRET', false),
    ];

    expect($this->resolver->keysUsedInConfig($calls))->toBe(['APP_NAME']);
});

it('deduplicates keys used multiple times in config', function () {
    $calls = [
        call('APP_KEY', true),
        call('APP_KEY', true),
    ];

    expect($this->resolver->keysUsedInConfig($calls))->toHaveCount(1);
});

it('ignores calls with null key in config', function () {
    $nullCall = new EnvCall(file: '/config/app.php', line: 5, key: null, inConfig: true);

    expect($this->resolver->keysUsedInConfig([$nullCall]))->toHaveCount(0);
});

// missingFromExample

it('returns keys used in config but absent from example', function () {
    $usedInConfig = ['APP_NAME', 'FEATURE_NEW_CHECKOUT'];
    $exampleKeys = ['APP_NAME' => 'Laravel'];

    $missing = $this->resolver->missingFromExample($usedInConfig, $exampleKeys);

    expect($missing)->toBe(['FEATURE_NEW_CHECKOUT']);
});

it('returns empty when all config keys are in example', function () {
    $usedInConfig = ['APP_NAME', 'DB_HOST'];
    $exampleKeys = ['APP_NAME' => 'Laravel', 'DB_HOST' => 'localhost'];

    expect($this->resolver->missingFromExample($usedInConfig, $exampleKeys))->toHaveCount(0);
});

it('returns all keys as missing when example is empty', function () {
    $usedInConfig = ['APP_NAME', 'DB_HOST'];

    expect($this->resolver->missingFromExample($usedInConfig, []))->toBe(['APP_NAME', 'DB_HOST']);
});

// unusedInExample

it('returns example keys never referenced in any env() call', function () {
    $exampleKeys = ['APP_NAME' => 'Laravel', 'OLD_PAYMENT_PROVIDER_KEY' => ''];
    $calls = [call('APP_NAME', true)];

    $unused = $this->resolver->unusedInExample($exampleKeys, $calls);

    expect($unused)->toBe(['OLD_PAYMENT_PROVIDER_KEY']);
});

it('returns empty when all example keys are referenced', function () {
    $exampleKeys = ['APP_NAME' => 'Laravel', 'DB_HOST' => 'localhost'];
    $calls = [call('APP_NAME', true), call('DB_HOST', false)];

    expect($this->resolver->unusedInExample($exampleKeys, $calls))->toHaveCount(0);
});

it('counts a key as used even when referenced outside config', function () {
    $exampleKeys = ['STRIPE_KEY' => 'your-key-here'];
    // referenced in app code (not ideal, but still "used" for drift purposes)
    $calls = [call('STRIPE_KEY', false)];

    expect($this->resolver->unusedInExample($exampleKeys, $calls))->toHaveCount(0);
});

it('returns all example keys as unused when there are no env() calls', function () {
    $exampleKeys = ['APP_NAME' => 'Laravel', 'OLD_KEY' => ''];

    $unused = $this->resolver->unusedInExample($exampleKeys, []);

    expect($unused)->toBe(['APP_NAME', 'OLD_KEY']);
});

it('ignores null-key calls when checking unused example keys', function () {
    $exampleKeys = ['APP_NAME' => 'Laravel'];
    $nullCall = new EnvCall(file: '/app/foo.php', line: 1, key: null, inConfig: false);

    $unused = $this->resolver->unusedInExample($exampleKeys, [$nullCall]);

    expect($unused)->toBe(['APP_NAME']);
});
