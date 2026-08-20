<?php

use Phoenix1331\LaravelEnvAudit\Scanning\AttributeResolver;

beforeEach(function () {
    $this->tmpDir = sys_get_temp_dir().'/env-attr-tests-'.md5(spl_object_id($this).'-'.microtime());
    mkdir($this->tmpDir, 0755, true);
});

afterEach(function () {
    if (! is_dir($this->tmpDir)) {
        return;
    }
    foreach (glob($this->tmpDir.'/*') ?: [] as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    rmdir($this->tmpDir);
});

function writeAttrFixture(string $dir, string $name, string $source): string
{
    $path = $dir.'/'.$name;
    file_put_contents($path, "<?php\n".$source);

    return $path;
}

// Attribute detection

it('detects WithoutEnvAudit attribute on a class', function () {
    $path = writeAttrFixture($this->tmpDir, 'Provider.php', '
        use Phoenix1331\LaravelEnvAudit\Attributes\WithoutEnvAudit;
        #[WithoutEnvAudit("legacy bootstrap requires runtime env", expires: "2099-01-01")]
        class TenantBootstrapProvider {}
    ');

    $resolver = new AttributeResolver('2026-01-01');
    $entries = $resolver->resolve([$path]);

    expect($entries)->toHaveCount(1);
    expect($entries[0]->reason)->toBe('legacy bootstrap requires runtime env');
    expect($entries[0]->expires)->toBe('2099-01-01');
    expect($entries[0]->expired)->toBeFalse();
    expect($entries[0]->source)->toBe('attribute');
});

it('marks attribute as expired when expires date is in the past', function () {
    $path = writeAttrFixture($this->tmpDir, 'Provider.php', '
        use Phoenix1331\LaravelEnvAudit\Attributes\WithoutEnvAudit;
        #[WithoutEnvAudit("temporary workaround", expires: "2020-01-01")]
        class OldProvider {}
    ');

    $resolver = new AttributeResolver('2026-08-01');
    $entries = $resolver->resolve([$path]);

    expect($entries[0]->expired)->toBeTrue();
});

it('detects WithoutEnvAudit on a method', function () {
    $path = writeAttrFixture($this->tmpDir, 'Service.php', '
        use Phoenix1331\LaravelEnvAudit\Attributes\WithoutEnvAudit;
        class MyService {
            #[WithoutEnvAudit("needed before config is loaded")]
            public function boot() { return env("APP_NAME"); }
        }
    ');

    $resolver = new AttributeResolver('2026-01-01');
    $entries = $resolver->resolve([$path]);

    expect($entries)->toHaveCount(1);
    expect($entries[0]->source)->toBe('attribute');
});

it('returns no entries for files with no attribute or comment', function () {
    $path = writeAttrFixture($this->tmpDir, 'Plain.php', '
        class Plain {
            public function go() { return env("APP_NAME"); }
        }
    ');

    $resolver = new AttributeResolver;
    $entries = $resolver->resolve([$path]);

    expect($entries)->toHaveCount(0);
});

// Inline comment detection

it('detects env-audit-ignore inline comment', function () {
    $path = writeAttrFixture($this->tmpDir, 'Routes.php', '
        // env-audit-ignore: still required by legacy queue worker, ticket JIRA-5190
        $driver = env("LEGACY_CACHE_DRIVER");
    ');

    $resolver = new AttributeResolver;
    $entries = $resolver->resolve([$path]);

    expect($entries)->toHaveCount(1);
    expect($entries[0]->reason)->toContain('legacy queue worker');
    expect($entries[0]->source)->toBe('inline-comment');
    expect($entries[0]->expired)->toBeFalse();
});

it('returns empty for files that do not exist', function () {
    $resolver = new AttributeResolver;
    $entries = $resolver->resolve(['/nonexistent/file.php']);

    expect($entries)->toHaveCount(0);
});

// isCovered

it('covers an env() call matched by an active inline-comment entry', function () {
    $path = writeAttrFixture($this->tmpDir, 'Routes.php', '
        // env-audit-ignore: needed here
        $x = env("SOME_KEY");
    ');

    $resolver = new AttributeResolver;
    $entries = $resolver->resolve([$path]);

    expect($entries)->toHaveCount(1);
    expect($resolver->isCovered($path, $entries[0]->line, $entries))->toBeTrue();
});

it('does not cover a different line with an inline-comment entry', function () {
    $path = writeAttrFixture($this->tmpDir, 'Routes.php', '
        // env-audit-ignore: needed here
        $x = env("SOME_KEY");
    ');

    $resolver = new AttributeResolver;
    $entries = $resolver->resolve([$path]);

    expect($resolver->isCovered($path, 999, $entries))->toBeFalse();
});

it('does not cover when the attribute is expired', function () {
    $path = writeAttrFixture($this->tmpDir, 'Provider.php', '
        use Phoenix1331\LaravelEnvAudit\Attributes\WithoutEnvAudit;
        #[WithoutEnvAudit("old workaround", expires: "2020-01-01")]
        class OldProvider {
            public function boot() { return env("APP_NAME"); }
        }
    ');

    $resolver = new AttributeResolver('2026-08-01');
    $entries = $resolver->resolve([$path]);

    expect($resolver->isCovered($path, 5, $entries))->toBeFalse();
});
