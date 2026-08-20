<?php

namespace Phoenix1331\LaravelEnvAudit;

use Illuminate\Support\ServiceProvider;
use Phoenix1331\LaravelEnvAudit\Console\EnvAuditRunCommand;

class LaravelEnvAuditServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/env-audit.php', 'env-audit');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/env-audit.php' => config_path('env-audit.php'),
            ], 'env-audit-config');

            $this->commands([
                EnvAuditRunCommand::class,
            ]);
        }
    }
}
