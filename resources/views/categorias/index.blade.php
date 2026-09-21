@extends('layouts.app')

@section('title', 'Categorias')

@section('content')
    <header class="page-heading">
        <div><div class="eyebrow">Catálogo</div><h1>Categorias</h1><p>Crie uma estrutura simples para localizar e organizar o acervo.</p></div>
        <div class="page-actions"><a href="{{ route('categorias.create') }}" class="btn btn-primary">Cadastrar categoria</a></div>
    </header>
    <section class="panel" aria-label="Busca de categorias">
        <form action="{{ route('categorias.index') }}" method="GET" class="filter-form">
            <div class="field"><label for="q">Buscar categoria</label><input id="q" name="q" type="search" class="form-control" value="{{ request('q') }}" placeholder="Nome ou descrição" maxlength="255"></div>
            <div class="form-actions"><button type="submit" class="btn btn-secondary">Buscar</button>@if(request()->filled('q'))<a href="{{ route('categorias.index') }}" class="btn btn-ghost">Limpar</a>@endif</div>
        </form>
    </section>
    <section class="panel">
        <div class="toolbar"><p class="muted">{{ $categorias->total() }} {{ $categorias->total() === 1 ? 'categoria encontrada' : 'categorias encontradas' }}</p></div>
        @if($categorias->isEmpty())
            <div class="empty-state"><h2>Nenhuma categoria encontrada</h2><p>Crie a primeira categoria para classificar os livros do acervo.</p><a href="{{ route('categorias.create') }}" class="btn btn-primary">Cadastrar categoria</a></div>
        @else
            <div class="table-wrap"><table class="data-table"><thead><tr><th scope="col">Categoria</th><th scope="col">Descrição</th><th scope="col">Livros no acervo</th><th scope="col" class="table-actions">Ações</th></tr></thead><tbody>
                @foreach($categorias as $categoria)
                    <tr>
                        <td><a href="{{ route('categorias.show', $categoria) }}" class="text-link">{{ $categoria->nome }}</a></td>
                        <td>{{ $categoria->descricao ?: 'Sem descrição' }}</td>
                        <td>{{ $categoria->livros_count }} {{ $categoria->livros_count === 1 ? 'livro' : 'livros' }}</td>
                        <td><div class="row-actions"><a href="{{ route('categorias.edit', $categoria) }}" class="btn btn-secondary btn-sm">Editar</a><form action="{{ route('categorias.destroy', $categoria) }}" method="POST" data-confirm="Remover a categoria &quot;{{ $categoria->nome }}&quot;? Categorias com livros cadastrados não podem ser removidas.">@csrf @method('DELETE')<button type="submit" class="btn btn-danger btn-sm">Remover</button></form></div></td>
                    </tr>
                @endforeach
            </tbody></table></div>
            {{ $categorias->appends(request()->query())->links() }}
        @endif
    </section>
@endsection
