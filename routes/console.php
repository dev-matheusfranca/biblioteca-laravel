<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('reservas:expirar')->everyMinute()->withoutOverlapping(5);
Schedule::command('operacoes:heartbeat')->everyMinute();
Schedule::command('operacoes:limpar-metricas')->hourly()->withoutOverlapping(15);
Schedule::command('comunicacoes:preparar')->everyMinute()->withoutOverlapping(5);
Schedule::command('outbox:publicar')->everyMinute()->withoutOverlapping(5);
Schedule::command('horizon:snapshot')->everyFiveMinutes()->withoutOverlapping(15);
Schedule::command('api:idempotency-clean')->hourly()->withoutOverlapping(15);
Schedule::command('sanctum:prune-expired --hours=0')->hourly()->withoutOverlapping(15);
