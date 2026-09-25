<?php

return [
    'enabled' => env('CATALOG_CACHE_ENABLED', true),
    'store' => env('CATALOG_CACHE_STORE', 'catalog'),
    'revision_store' => env('CATALOG_REVISION_STORE', env('CACHE_STORE', 'database')),
    'ttl_seconds' => 60,
];
