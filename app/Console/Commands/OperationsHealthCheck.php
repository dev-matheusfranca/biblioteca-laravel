<?php

namespace App\Console\Commands;

use App\Services\Operations\OperationsHealth;
use Illuminate\Console\Command;

class OperationsHealthCheck extends Command
{
    protected $signature = 'operacoes:saude {--json : Emite uma resposta própria para healthcheck} {--scheduler-only : Verifica somente o heartbeat do agendador} {--without-scheduler : Não exige heartbeat durante a inicialização do app}';

    protected $description = 'Verifica dependências e filas sem expor a configuração operacional.';

    public function handle(OperationsHealth $health): int
    {
        $schedulerOnly = (bool) $this->option('scheduler-only');
        if ($schedulerOnly) {
            $scheduler = $health->schedulerReport();
            $report = ['dependencies' => ['scheduler' => $scheduler]];
            $healthy = $scheduler['healthy'];
        } else {
            $report = $health->report(! (bool) $this->option('without-scheduler'));
            $healthy = $report['healthy'];
        }

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($schedulerOnly
                ? ['status' => $healthy ? 'healthy' : 'degraded', 'scheduler' => $report['dependencies']['scheduler']]
                : ['status' => $healthy ? 'healthy' : 'degraded'] + $report, JSON_UNESCAPED_SLASHES));
        } else {
            if (! $schedulerOnly) {
                $this->components->twoColumnDetail('Banco de dados', $report['dependencies']['database']['healthy'] ? 'ok' : 'indisponível');
                $this->components->twoColumnDetail('Fila Redis', $report['dependencies']['redis_queue']['healthy'] ? 'ok' : 'indisponível');
                $this->components->twoColumnDetail('Cache operacional', $report['dependencies']['cache']['healthy'] ? 'ok' : 'indisponível');
                $this->components->twoColumnDetail('Cache de catálogo', $report['dependencies']['catalog_cache']['healthy'] ? 'ok' : 'indisponível');
                if ($report['alerts'] !== []) {
                    $this->warn('Alertas: '.implode(', ', $report['alerts']));
                }
            }
            $this->components->twoColumnDetail('Heartbeat do agendador', $report['dependencies']['scheduler']['healthy'] ? 'ok' : 'atrasado');
        }

        return $healthy ? self::SUCCESS : self::FAILURE;
    }
}
