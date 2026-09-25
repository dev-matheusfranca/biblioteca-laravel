<?php

namespace Tests\Integration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class InfrastructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_mysql_connection_uses_isolated_database_and_rolls_back_writes(): void
    {
        $this->assertSame('mysql', DB::getDriverName());
        $this->assertSame('biblioteca_testing', DB::selectOne('SELECT DATABASE() AS name')->name);
        DB::beginTransaction();
        $id = DB::table('autores')->insertGetId(['nome' => 'Rollback de integração']);
        $this->assertDatabaseHas('autores', ['id' => $id]);
        DB::rollBack();
        $this->assertDatabaseMissing('autores', ['id' => $id]);
    }

    public function test_redis_can_store_and_expire_an_isolated_key(): void
    {
        $this->assertContains(config('database.redis.default.host'), ['127.0.0.1', 'redis']);
        $this->assertSame('15', (string) config('database.redis.default.database'));
        $this->assertSame('biblioteca_testing:', config('database.redis.options.prefix'));
        $this->assertEmpty(config('database.redis.default.url'));
        $this->assertNull(config('database.redis.default.password'));
        $key = 'testing:'.bin2hex(random_bytes(12));
        try {
            Redis::setex($key, 30, 'ready');
            $this->assertSame('ready', Redis::get($key));
            $this->assertGreaterThan(0, Redis::ttl($key));
        } finally {
            Redis::del($key);
        }
    }
}
