<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Global switch
    |--------------------------------------------------------------------------
    */
    'enabled' => true,

    /*
    |--------------------------------------------------------------------------
    | Config path
    |--------------------------------------------------------------------------
    | The directory that is considered "inside config/" for isolation scoring.
    | env() calls in files under this path are not flagged as direct-usage.
    */
    'config_path' => config_path(),

    /*
    |--------------------------------------------------------------------------
    | Scan paths
    |--------------------------------------------------------------------------
    | Directories walked for env() call detection.
    */
    'scan_paths' => [
        app_path(),
        config_path(),
        base_path('routes'),
        base_path('bootstrap'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Ignore paths
    |--------------------------------------------------------------------------
    | Directories excluded entirely from scanning.
    */
    'ignore_paths' => [
        base_path('vendor'),
    ],

    /*
    |--------------------------------------------------------------------------
    | .env.example file path
    |--------------------------------------------------------------------------
    | Configurable for repos using .env.dist or similar.
    */
    'example_file' => base_path('.env.example'),

    /*
    |--------------------------------------------------------------------------
    | Fail-on categories
    |--------------------------------------------------------------------------
    | Which violation categories cause a non-zero exit code.
    | Options: direct-usage, possible-secret, missing-from-example, unused-in-example
    */
    'fail_on' => [
        'direct-usage',
        'possible-secret',
    ],

    /*
    |--------------------------------------------------------------------------
    | Secret heuristics
    |--------------------------------------------------------------------------
    */
    'secret_heuristics' => [
        'enabled' => true,

        // Extra provider-specific patterns beyond the built-in set.
        'patterns' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | HTML report
    |--------------------------------------------------------------------------
    */
    'html' => [
        'output_path' => storage_path('env-audit/report.html'),
        'title' => 'Env Audit Report',
    ],

    /*
    |--------------------------------------------------------------------------
    | Require ignore reasons
    |--------------------------------------------------------------------------
    | When true, every attribute-based or inline-comment ignore must carry a
    | documented reason string.
    */
    'require_ignore_reasons' => true,

];
