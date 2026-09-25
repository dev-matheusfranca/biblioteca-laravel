<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        $connection = $app['config']->get('database.default');
        $database = $app['db']->connection()->getDatabaseName();

        if (! $app->environment('testing') || ! (
            ($connection === 'sqlite' && $database === ':memory:')
            || ($connection === 'mysql' && $database === 'biblioteca_testing'
                && $app['config']->get('database.connections.mysql.username') === 'biblioteca_test'
                && in_array($app['config']->get('database.connections.mysql.host'), ['127.0.0.1', 'mysql'], true))
        )) {
            throw new \RuntimeException('Testes bloqueados: use SQLite em memória ou o banco isolado biblioteca_testing.');
        }

        return $app;
    }
}
