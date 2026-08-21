<?php

use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->tmpDir = sys_get_temp_dir().'/env-cmd-tests-'.md5(spl_object_id($this).'-'.microtime());
    mkdir($this->tmpDir.'/config', 0755, true);
    mkdir($this->tmpDir.'/app', 0755, true);

    // Use $this->app['config']->set so Testbench carries these into artisan() calls
    $this->app['config']->set('env-audit.scan_paths', [$this->tmpDir.'/config', $this->tmpDir.'/app']);
    $this->app['config']->set('env-audit.ignore_paths', []);
    $this->app['config']->set('env-audit.example_file', $this->tmpDir.'/.env.example');
    $this->app['config']->set('env-audit.config_path', $this->tmpDir.'/config');
    $this->app['config']->set('env-audit.fail_on', ['direct-usage', 'possible-secret']);
    $this->app['config']->set('env-audit.secret_heuristics.enabled', true);
    $this->app['config']->set('env-audit.secret_heuristics.patterns', []);
    $this->app['config']->set('env-audit.enabled', true);
});

afterEach(function () {
    foreach ([$this->tmpDir.'/config', $this->tmpDir.'/app', $this->tmpDir] as $dir) {
        if (! is_dir($dir)) {
            continue;
        }
        foreach (glob($dir.'/*') ?: [] as $f) {
            if (is_file($f)) {
                unlink($f);
            }
        }
        @rmdir($dir);
    }
});

function writeFixture(string $path, string $content): void
{
    file_put_contents($path, $content);
}

// Helpers

function exampleFile(string $dir, string $content): void
{
    writeFixture($dir.'/.env.example', $content);
}

function configFile(string $dir, string $name, string $content): void
{
    writeFixture($dir.'/config/'.$name, "<?php\n".$content);
}

function appFile(string $dir, string $name, string $content): void
{
    writeFixture($dir.'/app/'.$name, "<?php\n".$content);
}

// Basic invocation

it('exits 0 when there are no violations', function () {
    exampleFile($this->tmpDir, "APP_NAME=Laravel\n");
    configFile($this->tmpDir, 'app.php', "return ['name' => env('APP_NAME')];");

    $this->artisan('env-audit:run')->assertExitCode(0);
});

it('exits 1 when direct usage is in fail-on and violations exist', function () {
    exampleFile($this->tmpDir, "APP_NAME=Laravel\n");
    appFile($this->tmpDir, 'Service.php', "class S { public function go() { return env('APP_NAME'); } }");

    $this->artisan('env-audit:run')->assertExitCode(1);
});

it('exits 1 when possible secret is detected in fail-on', function () {
    $fakeKey = implode('', ['sk', '_', 'live', '_', 'abcdefghij1234567890']);
    exampleFile($this->tmpDir, "STRIPE_SECRET={$fakeKey}\n");

    $this->artisan('env-audit:run')->assertExitCode(1);
});

it('exits 0 when fail-on is overridden to exclude direct-usage', function () {
    exampleFile($this->tmpDir, "APP_NAME=Laravel\n");
    appFile($this->tmpDir, 'Service.php', "class S { public function go() { return env('APP_NAME'); } }");

    $this->artisan('env-audit:run', ['--fail-on' => 'possible-secret'])->assertExitCode(0);
});

// JSON output

it('outputs valid json when --json is passed', function () {
    exampleFile($this->tmpDir, "APP_NAME=Laravel\n");

    $output = $this->artisan('env-audit:run', ['--json' => true]);
    $output->assertExitCode(0);

    // Capture output via a fresh run
    $result = Artisan::call('env-audit:run', ['--json' => true]);
    $json = json_decode(Artisan::output(), true);

    expect($json)->toBeArray();
    expect($json)->toHaveKey('isolation_score');
    expect($json)->toHaveKey('direct_usage');
    expect($json)->toHaveKey('possible_secrets');
});

// HTML output

it('writes an html file when --html is passed', function () {
    exampleFile($this->tmpDir, "APP_NAME=Laravel\n");
    $htmlPath = $this->tmpDir.'/report.html';

    $this->artisan('env-audit:run', ['--html' => $htmlPath])->assertExitCode(0);

    expect(file_exists($htmlPath))->toBeTrue();
    expect(file_get_contents($htmlPath))->toContain('<!DOCTYPE html>');
});

it('html report never contains a real env example secret value', function () {
    $realValue = implode('', ['sk', '_', 'live', '_', 'abcdefghij1234567890']);
    exampleFile($this->tmpDir, "STRIPE_SECRET={$realValue}\n");
    $htmlPath = $this->tmpDir.'/report.html';

    $this->artisan('env-audit:run', ['--html' => $htmlPath]);

    $html = file_get_contents($htmlPath);
    expect($html)->not->toContain($realValue);
    expect($html)->toContain('sk_l');
    expect($html)->toContain('****');
});

// Disabled

it('exits 0 immediately when env-audit is disabled', function () {
    $this->app['config']->set('env-audit.enabled', false);
    appFile($this->tmpDir, 'Service.php', "class S { public function go() { return env('APP_NAME'); } }");

    $this->artisan('env-audit:run')->assertExitCode(0);
});

// --fail-on validation

it('exits 1 when --fail-on is passed an empty value', function () {
    exampleFile($this->tmpDir, "APP_NAME=Laravel\n");

    $this->artisan('env-audit:run', ['--fail-on' => ''])->assertExitCode(1);
});

it('exits 1 when --fail-on contains an unknown category', function () {
    exampleFile($this->tmpDir, "APP_NAME=Laravel\n");

    $this->artisan('env-audit:run', ['--fail-on' => 'not-a-real-category'])->assertExitCode(1);
});

// Missing from example

it('detects keys used in config but missing from example', function () {
    exampleFile($this->tmpDir, "APP_NAME=Laravel\n");
    configFile($this->tmpDir, 'features.php', "return ['checkout' => env('FEATURE_NEW_CHECKOUT')];");

    $this->app['config']->set('env-audit.fail_on', ['missing-from-example']);

    $this->artisan('env-audit:run')->assertExitCode(1);
});

// Unused in example

it('detects keys in example never referenced in code', function () {
    exampleFile($this->tmpDir, "APP_NAME=Laravel\nOLD_KEY=stale\n");
    configFile($this->tmpDir, 'app.php', "return ['name' => env('APP_NAME')];");

    $this->app['config']->set('env-audit.fail_on', ['unused-in-example']);

    $this->artisan('env-audit:run')->assertExitCode(1);
});

// Real .env drift gating

it('can gate on env-only-keys when real env drift check is enabled', function () {
    $envFile = $this->tmpDir.'/.env';
    file_put_contents($envFile, "APP_NAME=Laravel\nSECRET_ONLY_IN_ENV=value\n");
    exampleFile($this->tmpDir, "APP_NAME=Laravel\n");

    $this->app['config']->set('env-audit.drift.check_real_env', true);
    $this->app['config']->set('env-audit.drift.env_file', $envFile);

    $this->artisan('env-audit:run', ['--fail-on' => 'env-only-keys'])->assertExitCode(1);
});

it('can gate on example-only-keys when real env drift check is enabled', function () {
    $envFile = $this->tmpDir.'/.env';
    file_put_contents($envFile, "APP_NAME=Laravel\n");
    exampleFile($this->tmpDir, "APP_NAME=Laravel\nDOCUMENTED_BUT_MISSING=\n");

    $this->app['config']->set('env-audit.drift.check_real_env', true);
    $this->app['config']->set('env-audit.drift.env_file', $envFile);

    $this->artisan('env-audit:run', ['--fail-on' => 'example-only-keys'])->assertExitCode(1);
});
