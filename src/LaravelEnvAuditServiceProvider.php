<?php

namespace Phoenix1331\LaravelEnvAudit;

use Illuminate\Support\ServiceProvider;
use Phoenix1331\LaravelEnvAudit\Console\EnvAuditRunCommand;
use Phoenix1331\LaravelEnvAudit\Scanning\AttributeResolver;
use Phoenix1331\LaravelEnvAudit\Scanning\ConfigUsageResolver;
use Phoenix1331\LaravelEnvAudit\Scanning\EnvFileParser;
use Phoenix1331\LaravelEnvAudit\Scanning\EnvUsageScanner;

class LaravelEnvAuditServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/env-audit.php', 'env-audit');

        $this->app->singleton(EnvUsageScanner::class);
        $this->app->singleton(EnvFileParser::class);
        $this->app->singleton(ConfigUsageResolver::class);
        $this->app->singleton(AttributeResolver::class);
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
