<?php

// Fail before booting Laravel if a cached operational configuration could leak in.
if (is_file(dirname(__DIR__).'/bootstrap/cache/config.php')) {
    throw new RuntimeException('Remova explicitamente o cache de configuração antes de executar testes ou análise.');
}

$environment = ['APP_ENV' => 'testing', 'HORIZON_PREFIX' => 'biblioteca_testing_horizon:', 'DB_URL' => '', 'DATABASE_URL' => '', 'MAIL_MAILER' => 'array'];

if (getenv('BIBLIOTECA_TEST_DRIVER') === 'mysql') {
    $host = getenv('BIBLIOTECA_TEST_DB_HOST');
    $redisHost = getenv('BIBLIOTECA_TEST_REDIS_HOST');
    $password = getenv('BIBLIOTECA_TEST_DB_PASSWORD');

    if (! in_array($host, ['127.0.0.1', 'mysql'], true)
        || ! in_array($redisHost, ['127.0.0.1', 'redis'], true)
        || ! is_string($password) || $password === '') {
        throw new RuntimeException('Integração exige conexões locais explícitas BIBLIOTECA_TEST_DB_HOST, BIBLIOTECA_TEST_DB_PASSWORD e BIBLIOTECA_TEST_REDIS_HOST. Use o serviço Compose tests.');
    }

    $environment += [
        'DB_CONNECTION' => 'mysql', 'DB_HOST' => $host, 'DB_PORT' => '3306',
        'DB_DATABASE' => 'biblioteca_testing', 'DB_USERNAME' => 'biblioteca_test', 'DB_PASSWORD' => $password,
        'REDIS_URL' => '', 'REDIS_HOST' => $redisHost, 'REDIS_PORT' => '6379',
        'REDIS_USERNAME' => '(null)', 'REDIS_PASSWORD' => '(null)',
        'REDIS_DB' => '15', 'REDIS_CACHE_DB' => '15', 'REDIS_PREFIX' => 'biblioteca_testing:',
        'REDIS_CLIENT' => 'phpredis',
    ];
}

foreach ($environment as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $_SERVER[$key] = $value;
}

require_once dirname(__DIR__).'/vendor/autoload.php';
