<?php

use Phoenix1331\LaravelEnvAudit\Scanning\EnvFileParser;

beforeEach(function () {
    $this->parser = new EnvFileParser;
    $this->tmpDir = sys_get_temp_dir().'/env-audit-tests-'.md5(spl_object_id($this).'-'.microtime());
    mkdir($this->tmpDir, 0755, true);
});

afterEach(function () {
    if (! is_dir($this->tmpDir)) {
        return;
    }
    foreach (glob($this->tmpDir.'/*') ?: [] as $file) {
        unlink($file);
    }
    rmdir($this->tmpDir);
});

function writeFile(string $dir, string $name, string $content): string
{
    $path = $dir.'/'.$name;
    file_put_contents($path, $content);

    return $path;
}

// parseKeys

it('returns empty array for missing file', function () {
    $result = $this->parser->parseKeys('/nonexistent/.env');
    expect($result)->toBe([]);
});

it('parses simple key=value lines into keys only', function () {
    $path = writeFile($this->tmpDir, '.env', "APP_NAME=Laravel\nAPP_ENV=local\n");
    $result = $this->parser->parseKeys($path);
    expect($result)->toHaveKeys(['APP_NAME', 'APP_ENV']);
    expect($result['APP_NAME'])->toBeNull();
    expect($result['APP_ENV'])->toBeNull();
});

it('skips comment lines', function () {
    $path = writeFile($this->tmpDir, '.env', "# comment\nAPP_KEY=secret\n");
    $result = $this->parser->parseKeys($path);
    expect($result)->toHaveKeys(['APP_KEY']);
    expect($result)->not->toHaveKey('# comment');
});

it('skips blank lines', function () {
    $path = writeFile($this->tmpDir, '.env', "\nAPP_NAME=foo\n\n");
    $result = $this->parser->parseKeys($path);
    expect($result)->toHaveKeys(['APP_NAME']);
    expect(count($result))->toBe(1);
});

it('skips lines without an equals sign', function () {
    $path = writeFile($this->tmpDir, '.env', "INVALID_LINE\nAPP_NAME=foo\n");
    $result = $this->parser->parseKeys($path);
    expect($result)->not->toHaveKey('INVALID_LINE');
    expect($result)->toHaveKeys(['APP_NAME']);
});

it('handles KEY= with empty value', function () {
    $path = writeFile($this->tmpDir, '.env', "DATABASE_URL=\n");
    $result = $this->parser->parseKeys($path);
    expect($result)->toHaveKeys(['DATABASE_URL']);
});

// parseExample

it('returns key and value for example file', function () {
    $path = writeFile($this->tmpDir, '.env.example', "STRIPE_KEY=your-key-here\n");
    $result = $this->parser->parseExample($path);
    expect($result)->toHaveKey('STRIPE_KEY');
    expect($result['STRIPE_KEY'])->toBe('your-key-here');
});

it('strips double quotes from values', function () {
    $path = writeFile($this->tmpDir, '.env.example', 'APP_NAME="My App"'."\n");
    $result = $this->parser->parseExample($path);
    expect($result['APP_NAME'])->toBe('My App');
});

it('strips single quotes from values', function () {
    $path = writeFile($this->tmpDir, '.env.example', "APP_NAME='My App'\n");
    $result = $this->parser->parseExample($path);
    expect($result['APP_NAME'])->toBe('My App');
});

it('strips inline comments from unquoted values', function () {
    $path = writeFile($this->tmpDir, '.env.example', "APP_PORT=8080 # default port\n");
    $result = $this->parser->parseExample($path);
    expect($result['APP_PORT'])->toBe('8080');
});

it('returns empty string for key with no value', function () {
    $path = writeFile($this->tmpDir, '.env.example', "SECRET_KEY=\n");
    $result = $this->parser->parseExample($path);
    expect($result['SECRET_KEY'])->toBe('');
});

it('returns empty array for missing example file', function () {
    $result = $this->parser->parseExample('/nonexistent/.env.example');
    expect($result)->toBe([]);
});

it('never returns real env values from parseKeys', function () {
    $path = writeFile($this->tmpDir, '.env', "STRIPE_SECRET=sk_live_realkey123\n");
    $result = $this->parser->parseKeys($path);
    // values must all be null — real secrets never exposed
    foreach ($result as $value) {
        expect($value)->toBeNull();
    }
});
