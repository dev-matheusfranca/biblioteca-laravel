<?php

return [
    'name' => 'Biblioteca',
    'domain' => null,
    'path' => 'horizon',
    'use' => 'default',
    'prefix' => env('HORIZON_PREFIX', 'biblioteca_horizon:'),
    'middleware' => ['web', 'auth', 'active', 'can:manage-team'],
    'waits' => ['redis:communications' => 60, 'redis:default' => 60],
    'trim' => ['recent' => 60, 'pending' => 60, 'completed' => 60, 'recent_failed' => 10080, 'failed' => 10080, 'monitored' => 10080],
    'silenced' => [],
    'silenced_tags' => [],
    'metrics' => ['trim_snapshots' => ['job' => 24, 'queue' => 24]],
    'fast_termination' => false,
    'memory_limit' => 64,
    'defaults' => [
        'communications' => [
            'connection' => 'redis', 'queue' => ['communications', 'default'],
            'balance' => 'simple', 'maxProcesses' => 2,
            'maxTime' => 3600, 'maxJobs' => 1000, 'memory' => 128,
            'tries' => 3, 'timeout' => 30, 'backoff' => [5, 30, 120], 'nice' => 0,
        ],
    ],
    'environments' => [
        'local' => ['communications' => ['maxProcesses' => 2]],
        'production' => ['communications' => ['maxProcesses' => 2]],
        'testing' => ['communications' => ['maxProcesses' => 1]],
    ],
];
