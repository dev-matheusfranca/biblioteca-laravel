@extends('layouts.app')

@section('title', 'Fila atual de reservas')

@section('content')
    <header class="page-heading">
        <div><div class="eyebrow">Operação</div><h1>Fila atual de reservas</h1><p>Posição e espera das solicitações que ainda aguardam um exemplar.</p></div>
        @include('relatorios._nav')
    </header>

    <section class="panel" aria-label="Filtros da fila">
        <form action="{{ route('relatorios.fila') }}" method="GET" class="filter-form">
            <div class="field"><label for="q">Título</label><input class="form-control" id="q" name="q" type="search" maxlength="100" value="{{ request('q') }}" placeholder="Filtrar por título">@error('q')<p class="field-error">{{ $message }}</p>@enderror</div>
            <div class="field"><label for="per_page">Linhas por página</label><select class="form-control" id="per_page" name="per_page">@foreach([10, 25, 50] as $size)<option value="{{ $size }}" @selected($reservations->perPage() === $size)>{{ $size }}</option>@endforeach</select></div>
            <div class="form-actions"><button class="btn btn-secondary" type="submit">Aplicar filtros</button><a class="btn btn-ghost" href="{{ route('relatorios.fila') }}">Limpar</a></div>
        </form>
    </section>

    <section class="panel">
        <div class="toolbar">
            <div><h2>Solicitações aguardando</h2><p class="muted">Gerado em {{ $generatedAt->format('d/m/Y H:i') }} · {{ $reservations->total() }} item(ns)</p></div>
            <div class="page-actions"><a class="btn btn-secondary" href="{{ route('relatorios.fila.csv', request()->except('page')) }}">Exportar CSV</a></div>
        </div>
        <div class="panel-body"><p>A posição é calculada agora por título, na ordem de criação e ID. A espera é a idade atual da solicitação; o histórico não guarda tempo médio de alocação.</p></div>
        @if($reservations->isEmpty())
            <div class="empty-state"><h2>Nenhuma reserva aguardando</h2><p>Holds já disponíveis e reservas encerradas não fazem parte desta fila.</p></div>
        @else
            <div class="table-wrap"><table class="data-table"><thead><tr><th scope="col">Título</th><th scope="col">Posição atual</th><th scope="col">Solicitada em</th><th scope="col">Espera atual</th></tr></thead><tbody>
                @foreach($reservations as $reservation)
                    @php($hours = $report->waitingHours($reservation, $generatedAt))
                    <tr><td>{{ $reservation->titulo }}</td><td>#{{ $reservation->posicao }}</td><td>{{ $reservation->created_at->format('d/m/Y H:i') }}</td><td>{{ intdiv($hours, 24) }} dia(s) e {{ $hours % 24 }} h</td></tr>
                @endforeach
            </tbody></table></div>
            {{ $reservations->links() }}
        @endif
    </section>
@endsection
