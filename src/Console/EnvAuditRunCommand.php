<?php

namespace Phoenix1331\LaravelEnvAudit\Console;

use Illuminate\Console\Command;

class EnvAuditRunCommand extends Command
{
    protected $signature = 'env-audit:run
                            {--json : Output results as JSON}
                            {--html= : Write an HTML report to the given path}
                            {--fail-on= : Comma-separated violation categories that cause a non-zero exit code}';

    protected $description = 'Audit env() usage, .env.example drift, and possible secrets';

    public function handle(): int
    {
        // Implemented in Task 10
        $this->info('env-audit:run — not yet implemented');

        return self::SUCCESS;
    }
}
