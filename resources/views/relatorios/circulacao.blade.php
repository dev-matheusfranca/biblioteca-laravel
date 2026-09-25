@extends('layouts.app')

@section('title', 'Relatório de circulação')

@section('content')
    <header class="page-heading">
        <div>
            <div class="eyebrow">Operação</div>
            <h1>Relatórios da biblioteca</h1>
            <p>Compare fluxos do período com a posição histórica dos empréstimos na data de referência.</p>
        </div>
        @include('relatorios._nav')
    </header>

    <section class="panel" aria-label="Filtros do relatório">
        <form action="{{ route('relatorios.circulacao') }}" method="GET" class="filter-form">
            <div class="field"><label for="period_start">Início do período</label><input class="form-control" id="period_start" name="period_start" type="date" value="{{ old('period_start', $period->startDate()) }}" required>@error('period_start')<p class="field-error">{{ $message }}</p>@enderror</div>
            <div class="field"><label for="period_end">Fim do período</label><input class="form-control" id="period_end" name="period_end" type="date" value="{{ old('period_end', $period->endDate()) }}" required>@error('period_end')<p class="field-error">{{ $message }}</p>@enderror</div>
            <div class="field"><label for="reference_date">Data de referência</label><input class="form-control" id="reference_date" name="reference_date" type="date" value="{{ old('reference_date', $period->referenceDate()) }}" required>@error('reference_date')<p class="field-error">{{ $message }}</p>@enderror</div>
            <div class="field"><label for="q">Título</label><input class="form-control" id="q" name="q" type="search" maxlength="100" value="{{ request('q') }}" placeholder="Filtrar por título">@error('q')<p class="field-error">{{ $message }}</p>@enderror</div>
            <div class="field"><label for="population">Empréstimos detalhados</label><select class="form-control" id="population" name="population">@foreach($report::POPULATION_LABELS as $value => $label)<option value="{{ $value }}" @selected($population === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="field"><label for="per_page">Linhas por página</label><select class="form-control" id="per_page" name="per_page">@foreach([10, 25, 50] as $size)<option value="{{ $size }}" @selected($demand->perPage() === $size)>{{ $size }}</option>@endforeach</select></div>
            <div class="form-actions">
                <button class="btn btn-secondary" type="submit">Aplicar filtros</button>
                <a class="btn btn-ghost" href="{{ route('relatorios.circulacao') }}">Limpar</a>
            </div>
        </form>
    </section>

    <div class="stats-grid" aria-label="Indicadores de circulação">
        <div class="stat-item"><span class="stat-icon blue">@include('partials.icon', ['name' => 'arrows'])</span><div><span class="stat-label">EMPRÉSTIMOS NO PERÍODO</span><strong>{{ number_format($summary['checkouts'], 0, ',', '.') }}</strong><small>Retiradas registradas de {{ $period->start->format('d/m/Y') }} a {{ $period->end->format('d/m/Y') }}</small></div></div>
        <div class="stat-item"><span class="stat-icon lilac">@include('partials.icon', ['name' => 'book'])</span><div><span class="stat-label">RESERVAS SOLICITADAS</span><strong>{{ number_format($summary['reservation_requests'], 0, ',', '.') }}</strong><small>Solicitações criadas no período, inclusive as já encerradas</small></div></div>
        <div class="stat-item"><span class="stat-icon mint">@include('partials.icon', ['name' => 'check'])</span><div><span class="stat-label">ABERTOS NA REFERÊNCIA</span><strong>{{ number_format($summary['open_at_reference'], 0, ',', '.') }}</strong><small>Em andamento ao fim de {{ $period->reference->format('d/m/Y') }}</small></div></div>
        <div class="stat-item"><span class="stat-icon peach">@include('partials.icon', ['name' => 'clock'])</span><div><span class="stat-label">ATRASADOS NA REFERÊNCIA</span><strong>{{ number_format($summary['overdue_at_reference'], 0, ',', '.') }}</strong><small>Prazo histórico anterior à data de referência</small></div></div>
    </div>

    <section class="panel">
        <dl class="detail-grid">
            <div><dt>Devoluções físicas no período</dt><dd>{{ number_format($summary['returns'], 0, ',', '.') }}</dd></div>
            <div><dt>Encerramentos por perda no período</dt><dd>{{ number_format($summary['losses'], 0, ',', '.') }}</dd></div>
            <div><dt>Leitura dos números</dt><dd>Fluxos usam o período. Abertos e atrasados usam o fim do dia de referência em America/Sao_Paulo.</dd></div>
            <div><dt>Gerado em</dt><dd>{{ $generatedAt->format('d/m/Y H:i:s') }} · {{ config('app.timezone') }}</dd></div>
        </dl>
    </section>

    <section class="panel">
        <div class="toolbar">
            <div><div class="eyebrow">Demanda por título</div><h2>Empréstimos e reservas</h2><p class="muted">As duas contagens representam eventos distintos e não deduplicam leitores.</p></div>
            <a class="btn btn-secondary" href="{{ route('relatorios.circulacao.csv', request()->except(['page', 'demand_page', 'loans_page'])) }}">Exportar CSV</a>
        </div>
        @if($demand->isEmpty())
            <div class="empty-state"><h2>Sem movimentação no período</h2><p>Ajuste as datas para consultar outro intervalo.</p></div>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th scope="col">Título</th><th scope="col">Empréstimos no período</th><th scope="col">Reservas solicitadas no período</th></tr></thead>
                    <tbody>@foreach($demand as $book)<tr><td>{{ $book->titulo }}</td><td>{{ $book->emprestimos_periodo }}</td><td>{{ $book->reservas_periodo }}</td></tr>@endforeach</tbody>
                </table>
            </div>
            {{ $demand->links() }}
        @endif
    </section>

    <section class="panel">
        <div class="toolbar">
            <div><div class="eyebrow">Conciliação</div><h2>{{ $report::POPULATION_LABELS[$population] }}</h2><p class="muted">Sem nome ou e-mail do leitor. {{ $population === 'period' ? 'O prazo exibido é o prazo atual armazenado.' : 'O prazo histórico recompõe renovações posteriores à referência.' }}</p></div>
        </div>
        @if($loans->isEmpty())
            <div class="empty-state"><h2>Nenhum empréstimo nesta população</h2><p>Ajuste o período, a referência, o título ou a população consultada.</p></div>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th scope="col">ID operacional</th><th scope="col">Título</th><th scope="col">Retirada</th><th scope="col">Prazo aplicável</th><th scope="col">Encerramento</th></tr></thead>
                    <tbody>@foreach($loans as $loan)<tr><td>#{{ $loan->id }}</td><td>{{ $loan->titulo }}</td><td>{{ \Illuminate\Support\Carbon::parse($loan->data_locacao)->format('d/m/Y') }}</td><td>{{ \Illuminate\Support\Carbon::parse($loan->prazo_exibido)->format('d/m/Y') }}</td><td>{{ $loan->encerrado_em?->format('d/m/Y H:i') ?? 'Em aberto' }}</td></tr>@endforeach</tbody>
                </table>
            </div>
            {{ $loans->links() }}
        @endif
    </section>
@endsection
