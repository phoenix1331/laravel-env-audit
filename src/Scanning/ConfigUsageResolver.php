<?php

namespace Phoenix1331\LaravelEnvAudit\Scanning;

use Phoenix1331\LaravelEnvAudit\Data\EnvCall;

class ConfigUsageResolver
{
    /**
     * Build the set of keys referenced inside config files (inConfig === true)
     * that have a known, non-null key string.
     *
     * @param  array<EnvCall>  $calls
     * @return array<string> unique key names
     */
    public function keysUsedInConfig(array $calls): array
    {
        $keys = [];

        foreach ($calls as $call) {
            if ($call->inConfig && $call->key !== null) {
                $keys[$call->key] = true;
            }
        }

        return array_keys($keys);
    }

    /**
     * Keys used in config files but absent from .env.example.
     *
     * @param  array<string>  $usedInConfig  from keysUsedInConfig()
     * @param  array<string, string>  $exampleKeys  from EnvFileParser::parseExample()
     * @return array<string>
     */
    public function missingFromExample(array $usedInConfig, array $exampleKeys): array
    {
        $missing = [];

        foreach ($usedInConfig as $key) {
            if (! array_key_exists($key, $exampleKeys)) {
                $missing[] = $key;
            }
        }

        return $missing;
    }

    /**
     * Keys present in .env.example but never referenced by any env() call
     * anywhere in the scanned codebase (config or otherwise).
     *
     * @param  array<string, string>  $exampleKeys  from EnvFileParser::parseExample()
     * @param  array<EnvCall>  $calls  all env() calls found
     * @return array<string>
     */
    public function unusedInExample(array $exampleKeys, array $calls): array
    {
        $referencedKeys = [];

        foreach ($calls as $call) {
            if ($call->key !== null) {
                $referencedKeys[$call->key] = true;
            }
        }

        $unused = [];

        foreach (array_keys($exampleKeys) as $key) {
            if (! isset($referencedKeys[$key])) {
                $unused[] = $key;
            }
        }

        return $unused;
    }
}
