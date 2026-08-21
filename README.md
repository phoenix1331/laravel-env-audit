# laravel-env-audit

[![Latest Version on Packagist](https://img.shields.io/packagist/v/phoenix1331/laravel-env-audit.svg?style=flat-square)](https://packagist.org/packages/phoenix1331/laravel-env-audit)
[![PHP Version](https://img.shields.io/packagist/php-v/phoenix1331/laravel-env-audit.svg?style=flat-square)](https://packagist.org/packages/phoenix1331/laravel-env-audit)
[![Laravel](https://img.shields.io/badge/laravel-10%20|%2011%20|%2012%20|%2013-red?style=flat-square)](https://laravel.com)
[![Tests](https://img.shields.io/github/actions/workflow/status/phoenix1331/laravel-env-audit/tests.yml?label=tests&style=flat-square)](https://github.com/phoenix1331/laravel-env-audit/actions/workflows/tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/phoenix1331/laravel-env-audit.svg?style=flat-square)](https://packagist.org/packages/phoenix1331/laravel-env-audit)
[![License](https://img.shields.io/packagist/l/phoenix1331/laravel-env-audit.svg?style=flat-square)](LICENSE)

> Your `.env.example` is a promise to the next developer. This package checks whether you kept it.

Static analysis for your Laravel environment configuration. Catches `env()` calls made outside `config/` (which break silently after `config:cache`), `.env.example` drift, and possible secrets accidentally committed to the example file. Reports an isolation score, a categorised violation list, and a self-contained HTML report. Fails CI when configured thresholds are breached.

<img width="838" height="444" alt="Screenshot 2026-08-20 163113" src="https://github.com/user-attachments/assets/55ee528c-4d11-4161-bcca-853336585ea6" />


The second package in the [Laravel Audit family](https://github.com/phoenix1331), after [laravel-auth-audit](https://github.com/phoenix1331/laravel-auth-audit).

---

## The problem this solves

`env()` outside a config file is probably the single most common Laravel footgun. It works perfectly in local development, then breaks silently in production the moment someone runs `php artisan config:cache`, because cached config no longer reads from `.env` at all. Any `env()` call outside `config/` returns `null` from that point on, with no error, no warning, nothing in the logs. The kind of bug that passes code review, passes CI, passes staging, and only shows up hours after a production deploy.

A second, quieter version of the same problem is `.env.example` drift. New variables appear in production `.env` files and never make it into the example, so onboarding a new developer means discovering missing configuration one runtime error at a time.

A third, more serious version: `.env.example` is committed to git and sometimes copy-pasted from a real `.env` file, which is a surprisingly common place for a genuine secret to end up in a public repository, disguised as a placeholder.

None of Laravel's own tooling checks for any of this.

---

## Why not just use Larastan, env-sync, or gitleaks?

Each of those tools solves one piece of this problem. None combine all three.

| What you want | Larastan | env-sync packages | gitleaks | laravel-env-audit |
|---|---|---|---|---|
| Catch `env()` outside `config/` | Partial (rule not on by default) | No | No | Yes |
| `.env.example` drift detection | No | Yes | No | Yes |
| Secret heuristics against `.env.example` | No | No | Yes (git history) | Yes (current file, Laravel-aware) |
| Isolation score across all call sites | No | No | No | Yes |
| Configurable CI gate per category | No | No | No | Yes |
| Expiring bypass mechanism | No | No | No | Yes |
| Self-contained HTML report | No | No | No | Yes |

Larastan's `noEnvCallsOutsideOfConfigRule` exists but is not enabled by default and has no concept of `.env.example` state. The env-sync family compares your env files but does not know what your config layer expects. Gitleaks scans git history for secrets; this package scans the current `.env.example` as a Laravel developer would, understanding which values are placeholder-shaped and which are not. And none of them produce a score, a gate, or a report you can attach to a PR.

---

## Design principles

- AST-based static analysis using `nikic/php-parser`: real parse-tree traversal rather than regex, so detection is trustworthy across heredocs, multiline calls, and dynamic arguments
- A coverage-gate pattern (isolation score + configurable fail-on thresholds) that mirrors the approach of `laravel-auth-audit`, making both packages read as a family
- A hard design constraint: the tool that catches leaked secrets must never itself become a place they leak from. The HTML report only ever renders masked values, and the real `.env` is never read beyond key names
- Attribute-based and inline-comment bypass mechanisms with mandatory reasons and expiry dates, so "temporary" exceptions cannot quietly become permanent

---

## Tech stack

- PHP 8.2+, Laravel 10/11/12/13
- `nikic/php-parser` ^5.0 for AST traversal
- Pest for testing, Orchestra Testbench for feature tests
- Laravel Pint for code style

---

## Getting started

### Prerequisites

- PHP 8.2+
- Laravel 10, 11, 12, or 13
- [`osv-scanner`](https://github.com/google/osv-scanner) installed locally for the pre-commit hook (if contributing)

### Installation

```bash
composer require phoenix1331/laravel-env-audit --dev
```

Publish the config file:

```bash
php artisan vendor:publish --tag=env-audit-config
```

### Running the audit

```bash
# Console output with isolation score and categorised violations
php artisan env-audit:run

# JSON output (pipe-friendly, suitable for tooling integration)
php artisan env-audit:run --json

# Write a self-contained HTML report
php artisan env-audit:run --html=storage/env-audit/report.html

# CI usage: exit 1 only on the dangerous categories
php artisan env-audit:run --fail-on=direct-usage,possible-secret
```

### Example output

```
  Isolation Score: 94% (17/18 env() calls live inside config/)

  x Direct usage (1)
    app/Services/LegacyBootstrap.php:12   env('APP_NAME') called outside config/

  x Possible secret in .env.example (1)
    STRIPE_SECRET=sk_l************************  high entropy value, may be a real secret

  ! Missing from .env.example (2)
    FEATURE_NEW_CHECKOUT   used in config/features.php:4, no matching .env.example entry
    MAIL_REPLY_TO          used in config/mail.php:31, no matching .env.example entry

  i Unused in .env.example (1)
    OLD_PAYMENT_PROVIDER_KEY   defined in .env.example, never referenced anywhere

  Failing: 2 error-level findings (direct-usage, possible-secret)
```

---

## How detection works

### 1. Direct env() usage outside config/ (Error by default)

The scanner walks every PHP file under `scan_paths` using `nikic/php-parser`, finds every `env()` call, and flags any that do not live inside the configured `config_path`. This is the dangerous category: it represents a production bug waiting to happen the moment someone runs `config:cache`.

### 2. Possible secret in .env.example (Error by default)

The `SecretHeuristicDetector` applies two checks against `.env.example` values specifically, never the real `.env`:

- **Pattern matching**: known provider-issued key shapes (`sk_live_`, `AKIA`, `ghp_`, `xoxb-`, `AIza`, `SG.`, and more)
- **Entropy**: Shannon entropy >= 3.5 bits/char on values >= 20 characters

Values that clearly look like placeholders (`your-*`, `change-me`, `<...>`, `{...}`) are skipped. The report only ever shows a masked preview (`sk_l************************`), never the full value.

### 3. Missing from .env.example (Warning by default)

Keys passed to `env()` inside config files but absent from `.env.example`. These represent undocumented configuration requirements that will break fresh environment setup.

### 4. Unused in .env.example (Info by default)

Keys present in `.env.example` but never referenced anywhere in the scanned codebase. Stale documentation from removed integrations.

All four thresholds are independently configurable.

---

## Configuration reference

Publish with `php artisan vendor:publish --tag=env-audit-config`.

| Key | Type | Default | Description |
|---|---|---|---|
| `enabled` | bool | `true` | Global on/off switch |
| `config_path` | string | `config_path()` | Directory considered "inside config/" for isolation scoring |
| `scan_paths` | array | `app/`, `config/`, `routes/`, `bootstrap/` | Directories walked for `env()` detection |
| `ignore_paths` | array | `vendor/` | Directories excluded entirely |
| `example_file` | string | `.env.example` | Path to the example file; use for `.env.dist` repos |
| `fail_on` | array | `['direct-usage', 'possible-secret']` | Categories that cause a non-zero exit code |
| `secret_heuristics.enabled` | bool | `true` | Toggle the secret-pattern/entropy check |
| `secret_heuristics.patterns` | array | `[]` | Extra regex patterns beyond the built-in set |
| `html.output_path` | string\|null | `null` | HTML report destination; set to a path to write the report on every run, or leave null and use `--html=` per-invocation |
| `html.title` | string | `'Env Audit Report'` | HTML report header text |
| `drift.check_real_env` | bool | `false` | Compare the real `.env` against `.env.example` by key names only; opt-in so CI environments without a `.env` are not broken |
| `drift.env_file` | string\|null | `null` | Path to the real `.env` file; defaults to `base_path('.env')` when null |
| `require_ignore_reasons` | bool | `true` | Forces every bypass to carry a documented reason |

---

## Bypass mechanism

For cases where a direct `env()` call is genuinely necessary, two bypass forms are supported. Both require a reason. Both support an `expires` date so exceptions cannot quietly become permanent: once the date passes, the tool reports the bypass as expired rather than honouring it.

### Attribute (for classes and methods)

```php
use Phoenix1331\LaravelEnvAudit\Attributes\WithoutEnvAudit;

#[WithoutEnvAudit(
    'Multi-tenant bootstrap requires TENANT_ID before config is cached, see ADR-012',
    expires: '2027-01-01'
)]
class TenantBootstrapProvider extends ServiceProvider
{
    public function register(): void
    {
        $tenantId = env('TENANT_ID');
        // ...
    }
}
```

### Inline comment (for files where attributes are not practical)

```php
// env-audit-ignore: legacy queue worker reads this before config boots, ticket INFRA-5190
$driver = env('LEGACY_CACHE_DRIVER');
```

Both bypasses are recorded in the report's exclusions section with their reasons. The count of active bypasses is reported as its own metric so the isolation score cannot be inflated by liberal use of the bypass instead of actually fixing call sites.

---

## CI recipe

```yaml
- name: Run env audit
  run: php artisan env-audit:run --fail-on=direct-usage,possible-secret
```

The command exits `0` when no categories in `fail-on` have findings, `1` otherwise. JSON output is available for downstream tooling:

```yaml
- name: Run env audit (JSON)
  run: php artisan env-audit:run --json > env-audit.json
```

---

## What this tool does not do

- **Scan the real `.env` file for secret values.** The tool reads `.env` for key names only, never values. No real secret ever appears in any output.
- **Scan git history.** That is what [Gitleaks](https://github.com/gitleaks/gitleaks) and [TruffleHog](https://github.com/trufflesecurity/trufflehog) are for. This package is specifically about the current `.env.example` and current codebase, framework-aware in a way generic secret scanners are not.
- **Boot the application.** All analysis is static: no service providers are registered, no database connections made.

---

## Why I built this

The `config:cache` footgun is a bug practically every Laravel developer who has shipped to production will recognise. The fix is always the same: move `env()` calls into `config/`. But there is no tooling that tells you where the unfixed ones are before they cost you an incident.

`.env.example` drift is subtler but causes real pain during onboarding and environment provisioning, and no existing tool cross-references it against what the config layer actually expects.

This package sits in a gap between Larastan (which knows nothing about env/config semantics), Enlightn (which checks a handful of production flags but does not do a full call-site audit), and Gitleaks (which is framework-unaware). It is narrow, fast, and designed to run on every CI job.

---

## Architecture notes

The design follows `nikic/php-parser`'s visitor pattern throughout. `EnvUsageScanner`, `AttributeResolver`, and the inline-comment extractor all use `NodeTraverser` with anonymous `NodeVisitorAbstract` subclasses rather than regex, which is why the detection is reliable across edge cases.

`EnvFileParser` has a deliberate split: `parseKeys()` returns `key => null` (real `.env`, values never stored), while `parseExample()` returns `key => value` (`.env.example` only, values needed by the heuristic). The type difference makes it structurally impossible to pass real secret values to the detector by mistake.

`SecretHeuristicDetector::mask()` is called before any value reaches a `PossibleSecret` DTO: the unmasked value never appears in any object, array, or output format.

---

## Contributing

```bash
git clone https://github.com/phoenix1331/laravel-env-audit
cd laravel-env-audit
composer install

composer test          # run the full test suite
composer test:unit     # unit tests only
composer test:feature  # feature tests only
composer lint          # auto-fix code style
composer lint:check    # check without fixing
```

Tests use [Pest](https://pestphp.com) and [Orchestra Testbench](https://github.com/orchestral/testbench). The `test-app/` directory (gitignored) contains a real Laravel 13 installation with all showcase scenarios pre-configured. Run `php artisan env-audit:run` inside it to see every category fire.

Pre-commit hooks enforce code style (Pint) and dependency auditing ([osv-scanner](https://github.com/google/osv-scanner)). Install osv-scanner before committing:

```bash
go install github.com/google/osv-scanner/cmd/osv-scanner@latest
```

---

## Roadmap

**v1.1:** CI matrix (PHP 8.2-8.4 x Laravel 10-13), bug fixes from the post-release audit (attribute line-range coverage, `--fail-on` validation, path normalisation for Windows, `require_ignore_reasons` enforcement, unparseable file surfacing), real `.env` drift via `parseKeys()`, and the competitor comparison section.

**v1.2:** Second-pass fixes: `html.output_path` defaults to `null` (opt-in); `--fail-on` validated before the scan so a typo doesn't complete the full audit before erroring; empty or unknown `--fail-on` values exit 1 with a clear error; drift gate categories error when `check_real_env` is disabled and warn when the `.env` file is missing; `env-only-keys`/`example-only-keys` wired into `countFor()` so drift can gate CI; `getEndLine() > 0` sentinel fix; `requireReasons` default aligned with config; `vendor/bin/pint --test` added to CI.

**v2:** Baseline file for legacy adoption (only new violations fail the build), GitHub Actions annotations and SARIF output, a `phoenix1331/env-audit-action` marketplace action, extended detection surface (`Env::get()`, `getenv()`, superglobals, Blade templates), secret heuristics v2 (per-key allowlist, key-name patterns, confidence levels), and sync helpers (`env-audit:sync`, `env-audit:ignores`).

**Longer term:** Pest/PHPUnit assertions, cross-package alignment with `laravel-auth-audit` under a shared audit-family brand.

---

## Licence

MIT. See [LICENSE](LICENSE).
