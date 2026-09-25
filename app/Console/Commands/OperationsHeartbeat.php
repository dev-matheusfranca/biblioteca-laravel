<?php

namespace App\Console\Commands;

use App\Services\Operations\OperationsHealth;
use Illuminate\Console\Command;
use Throwable;

class OperationsHeartbeat extends Command
{
    protected $signature = 'operacoes:heartbeat';

    protected $description = 'Registra o heartbeat do agendador operacional.';

    public function handle(OperationsHealth $health): int
    {
        try {
            $health->heartbeat();
        } catch (Throwable) {
            $this->error('Não foi possível registrar o heartbeat do agendador.');

            return self::FAILURE;
        }

        $this->components->info('Heartbeat operacional registrado.');

        return self::SUCCESS;
    }
}
