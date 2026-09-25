@extends('layouts.app')

@section('title', 'Saúde operacional')

@section('content')
<header class="page-heading">
    <div>
        <div class="eyebrow">Operação</div>
        <h1>Saúde operacional</h1>
        <p>Estado atual das dependências, do agendador e da caixa de saída.</p>
    </div>
    <span class="badge {{ $report['healthy'] ? 'badge-success' : 'badge-warning' }}">{{ $report['healthy'] ? 'Saudável' : 'Degradada' }}</span>
</header>

<section class="panel">
    <div class="toolbar"><h2>Dependências</h2><p class="muted">A página não expõe credenciais, DSNs, payloads ou dados de leitores.</p></div>
    <div class="table-wrap"><table class="data-table"><thead><tr><th scope="col">Componente</th><th scope="col">Estado</th><th scope="col">Detalhe operacional</th></tr></thead><tbody>
        <tr><td>Banco de dados</td><td>{{ $report['dependencies']['database']['healthy'] ? 'Disponível' : 'Indisponível' }}</td><td>Consulta de verificação</td></tr>
        <tr><td>Fila Redis</td><td>{{ $report['dependencies']['redis_queue']['healthy'] ? 'Disponível' : 'Indisponível' }}</td><td>{{ $report['dependencies']['redis_queue']['depth'] === null ? 'Sem leitura da fila' : $report['dependencies']['redis_queue']['depth'].' job(s) em communications' }}</td></tr>
        <tr><td>Cache operacional</td><td>{{ $report['dependencies']['cache']['healthy'] ? 'Disponível' : 'Indisponível' }}</td><td>Leitura e escrita temporária</td></tr>
        <tr><td>Cache de catálogo</td><td>{{ $report['dependencies']['catalog_cache']['healthy'] ? 'Disponível' : 'Indisponível' }}</td><td>Leitura e escrita temporária no store do catálogo</td></tr>
        <tr><td>Agendador</td><td>{{ $report['dependencies']['scheduler']['healthy'] ? 'Atualizado' : 'Atrasado' }}</td><td>{{ $report['dependencies']['scheduler']['seconds_ago'] === null ? 'Sem heartbeat registrado' : $report['dependencies']['scheduler']['seconds_ago'].' s desde o último heartbeat' }}</td></tr>
    </tbody></table></div>
</section>

<section class="panel">
    <div class="toolbar"><h2>Caixa de saída</h2><p class="muted">Pendências e falhas persistidas.</p></div>
    <div class="table-wrap"><table class="data-table"><thead><tr><th scope="col">Estado</th><th scope="col">Quantidade</th><th scope="col">Mais antigo</th></tr></thead><tbody>
        <tr><td>Pendente</td><td>{{ $report['outbox']['pending_count'] ?? 'Indisponível' }}</td><td>{{ $report['outbox']['oldest_pending_seconds'] === null ? '—' : $report['outbox']['oldest_pending_seconds'].' s' }}</td></tr>
        <tr><td>Falha terminal</td><td>{{ $report['outbox']['failed_count'] ?? 'Indisponível' }}</td><td>{{ $report['outbox']['oldest_failed_seconds'] === null ? '—' : $report['outbox']['oldest_failed_seconds'].' s' }}</td></tr>
        <tr><td>Processamento com lease vencido</td><td>{{ $report['outbox']['stale_processing_count'] ?? 'Indisponível' }}</td><td>{{ $report['outbox']['oldest_processing_seconds'] === null ? '—' : $report['outbox']['oldest_processing_seconds'].' s' }}</td></tr>
    </tbody></table></div>
</section>

@if($report['alerts'] !== [])
<section class="panel">
    <div class="toolbar"><h2>Alertas acionáveis</h2><p class="muted">Corrija a causa antes de reprocessar a caixa de saída.</p></div>
    <ul>
        @foreach($report['alerts'] as $alert)
            <li><code>{{ $alert }}</code>: {{ match($alert) {
                'database_unavailable' => 'confirme o banco e as migrations.',
                'outbox_unavailable' => 'a caixa de saída não pôde ser consultada.',
                'operational_cache_unavailable' => 'confirme o cache operacional.',
                'catalog_cache_unavailable' => 'confirme o Redis dedicado ao catálogo; o catálogo deve usar o fallback previsto.',
                'redis_queue_unavailable' => 'confirme Redis e Horizon.',
                'scheduler_stale' => 'confirme o processo schedule:work.',
                'outbox_failed' => 'corrija a entrega e reprocese as falhas pelo painel Comunicações.',
                'outbox_pending_overdue' => 'confirme worker e fila; tentativas futuras ainda respeitam o backoff.',
                'outbox_processing_stale' => 'investigue o worker antes de liberar uma nova tentativa.',
                default => 'verifique o runbook operacional.',
            } }}</li>
        @endforeach
    </ul>
</section>
@endif

<section class="panel">
    <div class="toolbar"><h2>Telemetria dos últimos {{ $report['http']['window_minutes'] }} minutos</h2><p class="muted">Métricas agregadas e expiráveis.</p></div>
    <div class="table-wrap"><table class="data-table"><thead><tr><th scope="col">Fluxo</th><th scope="col">Execuções</th><th scope="col">Erros</th><th scope="col">Latência média</th><th scope="col">Maior latência</th></tr></thead><tbody>
        <tr><td>HTTP</td><td>{{ $report['http']['count'] }}</td><td>{{ $report['http']['errors'] }}</td><td>{{ $report['http']['average_latency_ms'] }} ms</td><td>{{ $report['http']['max_latency_ms'] }} ms</td></tr>
        <tr><td>Processamentos de jobs</td><td>{{ $report['jobs']['count'] }}</td><td>{{ $report['jobs']['errors'] }}</td><td>{{ $report['jobs']['average_latency_ms'] }} ms</td><td>{{ $report['jobs']['max_latency_ms'] }} ms</td></tr>
    </tbody></table></div>
</section>
<p class="muted">Os processamentos registram tentativas do worker; um job concluído pode ser uma operação idempotente sem novo e-mail.</p>

<section class="panel">
    <div class="toolbar"><h2>Resposta operacional</h2></div>
    <ul>
        <li>Banco ou cache indisponível: verifique os serviços e as migrations antes de reiniciar processos.</li>
        <li>Fila Redis indisponível: confira o Redis e o Horizon; não descarte jobs para recuperar a fila.</li>
        <li>Heartbeat atrasado: confirme o processo <code>schedule:work</code> e execute <code>operacoes:heartbeat</code> apenas para diagnóstico.</li>
        <li>Falhas na caixa de saída: corrija a causa, use Comunicações para reprocessar e confirme o aviso no portal ou no Mailpit.</li>
    </ul>
</section>
@endsection
