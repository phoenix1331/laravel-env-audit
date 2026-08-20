# Changelog

All notable changes to this project will be documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.1.0] - 2026-08-20

### Fixed

- `--json` combined with `--html=` no longer pollutes stdout with the "HTML report written to" message, keeping JSON output pipe-safe
- `html.output_path` config key is now honoured: a report is written whenever the key is non-null, not only when the `--html` flag is passed
- `require_ignore_reasons` config key is now enforced: a bypass with an empty or missing reason is surfaced as a violation rather than silently honoured
- Attribute bypass coverage is now tightened to the line range of the attributed node (using `getEndLine()`), preventing a class-level `#[WithoutEnvAudit]` from silencing the rest of the file
- Unparseable files are now counted and surfaced in all formatters rather than silently dropped from the audit
- Path comparisons in `isInsideConfig()` and `isIgnored()` now normalise directory separators so the tool works correctly on Windows
- Unknown `--fail-on` category names now produce a non-zero exit with a descriptive error instead of silently treating the gate as passing
- `bootstrap/cache` added to the default `ignore_paths` alongside `vendor`

### Added

- GitHub Actions CI matrix across PHP 8.2/8.3/8.4 x Laravel 10/11/12/13 with `--prefer-lowest` and `--prefer-stable` runs
- `orchestra/testbench ^11` added to `require-dev` so Laravel 13 compatibility is actually testable
- `EnvFileParser::parseKeys()` is now wired into the command: keys in `.env` are compared against `.env.example` in both directions; opt-in via `drift.check_real_env` config (defaults false)
- README competitor comparison section explaining what this package provides that Larastan, env-sync packages, and gitleaks do not

---

## [1.0.0] - 2026-08-20

### Added

- `EnvUsageScanner`: AST-based detection of every `env()` call across configurable scan paths using `nikic/php-parser`. Records file, line, key, and whether the call lives inside `config/`.
- Isolation score: percentage of `env()` calls that live inside `config/`, reported on every run.
- `EnvFileParser`: parses `.env` for key names only (values are never stored); parses `.env.example` for key/value pairs used by the secret heuristic.
- `ConfigUsageResolver`: cross-references keys used in config files against `.env.example`, producing missing-from-example and unused-in-example sets.
- `SecretHeuristicDetector`: 15 built-in credential patterns (Stripe, AWS, GitHub, Slack, SendGrid, Twilio, Facebook, Google, and others) plus Shannon entropy check (threshold: 3.5 bits/char, minimum length: 20 chars). Placeholder values are excluded before either check runs. Masked preview only in all output.
- `#[WithoutEnvAudit]` PHP 8 attribute for class/method/function-level bypass with mandatory reason and optional `expires` date.
- `// env-audit-ignore:` inline comment bypass with mandatory reason.
- Expired bypass detection: bypasses whose `expires` date has passed are surfaced as a distinct report entry rather than silently honoured.
- `EnvAuditReport` DTO with `isolationScore()`, `countFor()`, and `hasViolationsIn()`.
- `ConsoleFormatter`: colour-coded output with per-category sections and bypass summary.
- `JsonFormatter`: structured JSON output suitable for piping to downstream tooling. Failure summary suppressed from stdout to keep output valid JSON.
- `HtmlFormatter`: self-contained dark-themed HTML report with sortable tables and masked secret previews.
- `env-audit:run` Artisan command with `--json`, `--html=path`, and `--fail-on=` flags.
- Config file (`config/env-audit.php`) covering: `enabled`, `config_path`, `scan_paths`, `ignore_paths`, `example_file`, `fail_on`, `secret_heuristics.enabled`, `secret_heuristics.patterns`, `html.output_path`, `html.title`, `require_ignore_reasons`.
- `vendor:publish` tag `env-audit-config`.
- Laravel 10, 11, 12, and 13 compatibility.
- 99 tests via Pest and Orchestra Testbench.
- Pre-commit hooks: Pint linting and osv-scanner dependency audit.

[1.1.0]: https://github.com/phoenix1331/laravel-env-audit/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/phoenix1331/laravel-env-audit/releases/tag/v1.0.0
