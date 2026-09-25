@extends('layouts.app')
@section('title', 'Catálogo público')
@section('content')
<header class="page-heading">
    <div><div class="eyebrow">Descubra sua próxima leitura</div><h1>Catálogo público</h1><p>Explore os títulos e consulte a disponibilidade na biblioteca.</p></div>
</header>
<section class="panel" aria-label="Filtros do catálogo">
    <form action="{{ route('catalogo.index') }}" method="GET" class="filter-form">
        <div class="field"><label for="q">Título, autor ou ISBN</label><input id="q" name="q" type="search" class="form-control" value="{{ request('q') }}" maxlength="255"></div>
        <div class="field"><label for="categoria">Categoria</label><select id="categoria" name="categoria" class="form-control"><option value="">Todas as categorias</option>@foreach($categorias as $categoria)<option value="{{ $categoria->id }}" @selected((string) request('categoria') === (string) $categoria->id)>{{ $categoria->nome }}</option>@endforeach</select></div>
        <div class="form-actions"><button class="btn btn-secondary" type="submit">Buscar</button><a href="{{ route('catalogo.index') }}" class="btn btn-ghost">Limpar</a></div>
    </form>
</section>
<section class="panel">
    <div class="toolbar"><p class="muted">{{ $livros->total() }} título(s) encontrado(s)</p></div>
    @if($livros->isEmpty())
        <div class="empty-state"><h2>Nenhum livro encontrado</h2><p>Tente outro termo ou categoria. Novos títulos aparecerão aqui quando estiverem no catálogo.</p></div>
    @else
        <div class="table-wrap"><table class="data-table"><thead><tr><th scope="col">Livro</th><th scope="col">Categoria</th><th scope="col">Disponibilidade</th></tr></thead><tbody>
        @foreach($livros as $livro)
            <tr><td><a class="text-link" href="{{ route('catalogo.show', $livro) }}">{{ $livro->titulo }}</a><span class="muted">{{ $livro->autor->nome ?? 'Autor não informado' }}</span></td><td>{{ $livro->categoria->nome ?? 'Sem categoria' }}</td><td><span class="badge {{ ($livro->usaExemplares() && $livro->quantidade_disponivel > 0) ? 'badge-success' : 'badge-warning' }}">{{ ($livro->usaExemplares() && $livro->quantidade_disponivel > 0) ? 'Disponível' : 'Indisponível no momento' }}</span></td></tr>
        @endforeach
        </tbody></table></div>
        {{ $livros->links() }}
    @endif
</section>
@endsection
