<?php

namespace Phoenix1331\LaravelEnvAudit\Scanning;

class EnvFileParser
{
    /**
     * Parse an .env-style file and return only the key names.
     *
     * Real values from .env are never stored or returned — only keys.
     * Values from .env.example are returned so the secret heuristic can inspect them.
     *
     * @return array<string, string|null> key => value (value is null when parsing keys-only mode)
     */
    public function parseKeys(string $path): array
    {
        if (! file_exists($path)) {
            return [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $keys = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (! str_contains($line, '=')) {
                continue;
            }

            [$key] = explode('=', $line, 2);
            $key = trim($key);

            if ($key === '' || ! preg_match('/^[A-Z][A-Z0-9_]*$/i', $key)) {
                continue;
            }

            $keys[$key] = null;
        }

        return $keys;
    }

    /**
     * Parse an .env.example file returning key => value pairs.
     *
     * Values are returned here because the SecretHeuristicDetector needs them.
     * This method must only ever be called against .env.example, never the real .env.
     *
     * @return array<string, string>
     */
    public function parseExample(string $path): array
    {
        if (! file_exists($path)) {
            return [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $entries = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
            $key = trim($key);
            $value = $this->unquote(trim($value));

            if ($key === '' || ! preg_match('/^[A-Z][A-Z0-9_]*$/i', $key)) {
                continue;
            }

            $entries[$key] = $value;
        }

        return $entries;
    }

    /**
     * Strip surrounding quotes from a value, matching dotenv conventions.
     */
    private function unquote(string $value): string
    {
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[-1];

            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                return substr($value, 1, -1);
            }
        }

        // Strip inline comments for unquoted values
        if (str_contains($value, ' #')) {
            $value = trim(explode(' #', $value, 2)[0]);
        }

        return $value;
    }
}
