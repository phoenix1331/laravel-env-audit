<?php

use Phoenix1331\LaravelEnvAudit\Data\EnvCall;
use Phoenix1331\LaravelEnvAudit\Scanning\EnvUsageScanner;

beforeEach(function () {
    $this->scanner = new EnvUsageScanner;
    $this->tmpDir = sys_get_temp_dir().'/env-scanner-tests-'.md5(spl_object_id($this).'-'.microtime());
    mkdir($this->tmpDir.'/config', 0755, true);
    mkdir($this->tmpDir.'/app', 0755, true);
});

afterEach(function () {
    $dirs = [$this->tmpDir.'/config', $this->tmpDir.'/app', $this->tmpDir];
    foreach ($dirs as $dir) {
        if (! is_dir($dir)) {
            continue;
        }
        foreach (glob($dir.'/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($dir);
    }
});

function writePhp(string $dir, string $name, string $source): string
{
    $path = $dir.'/'.$name;
    file_put_contents($path, "<?php\n".$source);

    return $path;
}

it('detects env() call outside config as not in config', function () {
    writePhp($this->tmpDir.'/app', 'Service.php', "
        class Service {
            public function boot() { return env('APP_NAME'); }
        }
    ");

    $calls = $this->scanner->scan(
        [$this->tmpDir.'/app'],
        [],
        $this->tmpDir.'/config'
    );

    expect($calls)->toHaveCount(1);
    expect($calls[0])->toBeInstanceOf(EnvCall::class);
    expect($calls[0]->key)->toBe('APP_NAME');
    expect($calls[0]->inConfig)->toBeFalse();
});

it('detects env() call inside config as in config', function () {
    writePhp($this->tmpDir.'/config', 'app.php', "
        return ['name' => env('APP_NAME', 'Laravel')];
    ");

    $calls = $this->scanner->scan(
        [$this->tmpDir.'/config'],
        [],
        $this->tmpDir.'/config'
    );

    expect($calls)->toHaveCount(1);
    expect($calls[0]->inConfig)->toBeTrue();
    expect($calls[0]->key)->toBe('APP_NAME');
});

it('records the correct line number', function () {
    $path = $this->tmpDir.'/app/Service.php';
    file_put_contents($path, "<?php\n\n\n\$x = env('DB_HOST');\n");

    $calls = $this->scanner->scan([$this->tmpDir.'/app'], [], $this->tmpDir.'/config');

    expect($calls[0]->line)->toBe(4);
});

it('records null key when env() is called with a variable argument', function () {
    writePhp($this->tmpDir.'/app', 'Dynamic.php', "
        \$key = 'APP_NAME';
        env(\$key);
    ");

    $calls = $this->scanner->scan([$this->tmpDir.'/app'], [], $this->tmpDir.'/config');

    expect($calls)->toHaveCount(1);
    expect($calls[0]->key)->toBeNull();
});

it('detects multiple env() calls in one file', function () {
    writePhp($this->tmpDir.'/config', 'mail.php', "
        return [
            'host' => env('MAIL_HOST'),
            'port' => env('MAIL_PORT'),
            'user' => env('MAIL_USERNAME'),
        ];
    ");

    $calls = $this->scanner->scan([$this->tmpDir.'/config'], [], $this->tmpDir.'/config');

    expect($calls)->toHaveCount(3);
    $keys = array_map(fn ($c) => $c->key, $calls);
    expect($keys)->toContain('MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME');
});

it('skips files in ignored paths', function () {
    writePhp($this->tmpDir.'/app', 'Legacy.php', "env('OLD_KEY');");

    $calls = $this->scanner->scan(
        [$this->tmpDir.'/app'],
        [$this->tmpDir.'/app'],
        $this->tmpDir.'/config'
    );

    expect($calls)->toHaveCount(0);
});

it('skips non-existent scan paths gracefully', function () {
    $calls = $this->scanner->scan(['/nonexistent/path'], [], $this->tmpDir.'/config');

    expect($calls)->toHaveCount(0);
});

it('ignores files with parse errors gracefully', function () {
    $path = $this->tmpDir.'/app/Broken.php';
    file_put_contents($path, '<?php this is not valid php }{}{');

    $calls = $this->scanner->scan([$this->tmpDir.'/app'], [], $this->tmpDir.'/config');

    expect($calls)->toHaveCount(0);
});

it('does not detect env as a method call', function () {
    writePhp($this->tmpDir.'/app', 'Model.php', "
        class Model {
            public function env(\$key) { return \$key; }
            public function boot() { return \$this->env('APP_NAME'); }
        }
    ");

    $calls = $this->scanner->scan([$this->tmpDir.'/app'], [], $this->tmpDir.'/config');

    expect($calls)->toHaveCount(0);
});

it('records the correct file path', function () {
    $path = writePhp($this->tmpDir.'/app', 'Service.php', "env('APP_KEY');");

    $calls = $this->scanner->scan([$this->tmpDir.'/app'], [], $this->tmpDir.'/config');

    expect($calls[0]->file)->toBe($path);
});
