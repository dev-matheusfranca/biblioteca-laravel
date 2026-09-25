@extends('layouts.app')

@section('title', 'Exemplares indisponíveis')

@section('content')
    <header class="page-heading">
        <div><div class="eyebrow">Operação</div><h1>Exemplares indisponíveis</h1><p>Motivo atual e exclusivo para cada unidade que não pode ser retirada.</p></div>
        @include('relatorios._nav')
    </header>

    <section class="panel" aria-label="Filtros do inventário">
        <form action="{{ route('relatorios.indisponiveis') }}" method="GET" class="filter-form">
            <div class="field"><label for="q">Título</label><input class="form-control" id="q" name="q" type="search" maxlength="100" value="{{ request('q') }}" placeholder="Filtrar por título">@error('q')<p class="field-error">{{ $message }}</p>@enderror</div>
            <div class="field"><label for="per_page">Linhas por página</label><select class="form-control" id="per_page" name="per_page">@foreach([10, 25, 50] as $size)<option value="{{ $size }}" @selected($copies->perPage() === $size)>{{ $size }}</option>@endforeach</select></div>
            <div class="form-actions"><button class="btn btn-secondary" type="submit">Aplicar filtros</button><a class="btn btn-ghost" href="{{ route('relatorios.indisponiveis') }}">Limpar</a></div>
        </form>
    </section>

    <section class="panel">
        <div class="toolbar">
            <div><h2>Posição atual do inventário</h2><p class="muted">Gerado em {{ $generatedAt->format('d/m/Y H:i') }} · {{ $copies->total() }} exemplar(es) indisponível(is)</p></div>
            <a class="btn btn-secondary" href="{{ route('relatorios.indisponiveis.csv', request()->except('page')) }}">Exportar CSV</a>
        </div>
        <div class="panel-body"><p>Quando uma unidade possui mais de uma condição, a classificação usa esta precedência: identificação pendente, manutenção, extravio, baixa, empréstimo aberto, hold de reserva, reconciliação e título inativo.</p></div>
        @if($copies->isEmpty())
            <div class="empty-state"><h2>Todos os exemplares estão disponíveis</h2><p>Nenhuma unidade física possui impedimento atual.</p></div>
        @else
            <div class="table-wrap"><table class="data-table"><thead><tr><th scope="col">Título</th><th scope="col">Código patrimonial</th><th scope="col">Motivo atual</th></tr></thead><tbody>
                @foreach($copies as $copy)<tr><td>{{ $copy->titulo }}</td><td>{{ $copy->codigo_patrimonial }}</td><td><span class="badge badge-warning">{{ $report->reasonLabel($copy) }}</span></td></tr>@endforeach
            </tbody></table></div>
            {{ $copies->links() }}
        @endif
    </section>
@endsection
