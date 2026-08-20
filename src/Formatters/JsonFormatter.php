<?php

namespace Phoenix1331\LaravelEnvAudit\Formatters;

use Phoenix1331\LaravelEnvAudit\Data\EnvAuditReport;

class JsonFormatter
{
    public function format(EnvAuditReport $report): string
    {
        $data = [
            'isolation_score' => $report->isolationScore(),
            'total_env_calls' => $report->totalEnvCalls,
            'config_env_calls' => $report->configEnvCalls,
            'direct_usage' => array_map(fn ($c) => [
                'file' => $c->file,
                'line' => $c->line,
                'key' => $c->key,
            ], $report->directUsageViolations),
            'possible_secrets' => array_map(fn ($s) => [
                'key' => $s->key,
                'masked_value' => $s->maskedValue,
                'reason' => $s->reason,
                'detail' => $s->detail,
            ], $report->possibleSecrets),
            'missing_from_example' => $report->missingFromExample,
            'unused_in_example' => $report->unusedInExample,
            'bypasses' => [
                'active' => array_map(fn ($i) => [
                    'file' => $i->file,
                    'line' => $i->line,
                    'reason' => $i->reason,
                    'expires' => $i->expires,
                    'source' => $i->source,
                ], $report->ignores),
                'expired' => array_map(fn ($i) => [
                    'file' => $i->file,
                    'line' => $i->line,
                    'reason' => $i->reason,
                    'expires' => $i->expires,
                    'source' => $i->source,
                ], $report->expiredIgnores),
            ],
        ];

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
