@extends('layouts.app')

@section('title', 'Empréstimos')

@section('content')
    <header class="page-heading">
        <div><div class="eyebrow">Circulação</div><h1>Empréstimos</h1><p>Acompanhe retiradas, devoluções previstas e pendências do acervo.</p></div>
        <div class="page-actions"><a href="{{ route('locacoes.create') }}" class="btn btn-primary">Registrar empréstimo</a></div>
    </header>

    <section class="panel" aria-label="Filtros de empréstimos">
        <form action="{{ route('locacoes.index') }}" method="GET" class="filter-form">
            <div class="field"><label for="q">Buscar empréstimo</label><input id="q" name="q" type="search" class="form-control" value="{{ request('q') }}" placeholder="Pessoa ou título" maxlength="255"></div>
            <div class="field"><label for="status">Situação</label><select id="status" name="status" class="form-control"><option value="">Todas as situações</option><option value="ativa" @selected(request('status') === 'ativa')>Ativa</option><option value="devolvida" @selected(request('status') === 'devolvida')>Devolvida</option><option value="atrasada" @selected(request('status') === 'atrasada')>Atrasada</option></select></div>
            <div class="form-actions"><button type="submit" class="btn btn-secondary">Filtrar</button>@if(request()->hasAny(['q', 'status']))<a href="{{ route('locacoes.index') }}" class="btn btn-ghost">Limpar</a>@endif</div>
        </form>
    </section>

    <section class="panel">
        <div class="toolbar"><p class="muted">{{ $locacoes->total() }} {{ $locacoes->total() === 1 ? 'empréstimo encontrado' : 'empréstimos encontrados' }}</p></div>
        @if($locacoes->isEmpty())
            <div class="empty-state"><h2>Nenhum empréstimo encontrado</h2><p>Registre uma retirada para acompanhar a circulação do acervo.</p><a href="{{ route('locacoes.create') }}" class="btn btn-primary">Registrar empréstimo</a></div>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th scope="col">Pessoa</th><th scope="col">Livro</th><th scope="col">Retirada</th><th scope="col">Devolução prevista</th><th scope="col">Situação</th><th scope="col" class="table-actions">Ações</th></tr></thead>
                    <tbody>
                        @foreach($locacoes as $locacao)
                            @php($situacao = $locacao->situacao_atual)
                            @php($statusClasses = ['ativa' => 'badge-success', 'devolvida' => 'badge-neutral', 'atrasada' => 'badge-warning'])
                            <tr>
                                <td>{{ $locacao->usuario->name ?? 'Usuário removido' }}<span class="muted">{{ $locacao->usuario->email ?? '' }}</span></td>
                                <td><a href="{{ route('livros.show', $locacao->livro) }}" class="text-link">{{ $locacao->livro->titulo ?? 'Livro removido' }}</a></td>
                                <td>{{ \Illuminate\Support\Carbon::parse($locacao->data_locacao)->format('d/m/Y') }}</td>
                                <td>{{ \Illuminate\Support\Carbon::parse($locacao->data_devolucao)->format('d/m/Y') }}</td>
                                <td><span class="badge {{ $statusClasses[$situacao] ?? 'badge-neutral' }}">{{ ucfirst($situacao) }}</span>@if($locacao->status === 'devolvida' && $locacao->data_devolvido)<span class="muted">Devolvido em {{ \Illuminate\Support\Carbon::parse($locacao->data_devolvido)->format('d/m/Y') }}</span>@endif</td>
                                <td><div class="row-actions">
                                    @if($locacao->status !== 'devolvida')
                                        <form action="{{ route('locacoes.devolver', $locacao) }}" method="POST" data-confirm="Confirmar a devolução de &quot;{{ $locacao->livro->titulo ?? 'este livro' }}&quot;?">
                                            @csrf
                                            <button type="submit" class="btn btn-primary btn-sm">Registrar devolução</button>
                                        </form>
                                    @endif
                                    <a href="{{ route('locacoes.show', $locacao) }}" class="btn btn-ghost btn-sm">Detalhes</a>
                                </div></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $locacoes->appends(request()->query())->links() }}
        @endif
    </section>
@endsection
