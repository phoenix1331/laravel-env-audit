<?php

namespace Phoenix1331\LaravelEnvAudit\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Phoenix1331\LaravelEnvAudit\LaravelEnvAuditServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            LaravelEnvAuditServiceProvider::class,
        ];
    }
}
