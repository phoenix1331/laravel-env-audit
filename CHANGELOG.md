# Changelog

All notable changes to this project will be documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

[1.0.0]: https://github.com/phoenix1331/laravel-env-audit/releases/tag/v1.0.0
