# Security Policy

## Supported versions

| Version | Supported |
|---------|-----------|
| 1.x     | Yes       |

## Reporting a vulnerability

Please do not report security vulnerabilities through public GitHub issues.

Send a description of the vulnerability to **darren@phoenix1331.co.uk**. Include:

- A description of the issue and its potential impact
- Steps to reproduce or a proof-of-concept
- Any suggested fix, if you have one

You will receive a response within 72 hours. If the issue is confirmed, a patch will be released as soon as possible and you will be credited in the changelog unless you prefer otherwise.

## Scope

This package performs static analysis only. It does not boot the Laravel application, make network requests, or connect to any external service. The attack surface is limited to:

- Maliciously crafted PHP files passed to the AST scanner
- Maliciously crafted `.env.example` files passed to the secret heuristic
- The HTML report output, which uses `htmlspecialchars` throughout to prevent XSS

The package explicitly never reads values from the real `.env` file. `EnvFileParser::parseKeys()` discards values at parse time; only key names are retained.

## Design constraints relevant to security

- Real `.env` values are never stored, logged, or rendered in any output format.
- `SecretHeuristicDetector::mask()` is called before any value reaches a DTO. The unmasked value never appears in any object or output.
- The HTML report is self-contained and makes no external requests.
