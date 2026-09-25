<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class CleanupOperationalMetrics extends Command
{
    protected $signature = 'operacoes:limpar-metricas';

    protected $description = 'Remove buckets expirados de telemetria do cache operacional em banco.';

    public function handle(): int
    {
        if (config('cache.default') !== 'database') {
            $this->components->info('O cache operacional atual não usa banco; nenhuma métrica precisa de limpeza.');

            return self::SUCCESS;
        }

        try {
            /** @var array{connection:string|null,table:string} $store */
            $store = config('cache.stores.database');
            $deleted = DB::connection($store['connection'] ?: config('database.default'))
                ->table($store['table'])
                ->where('key', 'like', config('cache.prefix').':operations:metrics:v1:%')
                ->where('expiration', '<=', now()->getTimestamp())
                ->delete();
        } catch (Throwable) {
            $this->error('Não foi possível limpar as métricas operacionais expiradas.');

            return self::FAILURE;
        }

        $this->components->info($deleted.' bucket(s) operacional(is) removido(s).');

        return self::SUCCESS;
    }
}
