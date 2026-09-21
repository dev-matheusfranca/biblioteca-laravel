@extends('layouts.app')

@section('title', 'Autores')

@section('content')
    <header class="page-heading">
        <div>
            <div class="eyebrow">Catálogo</div>
            <h1>Autores</h1>
            <p>Organize as pessoas que dão identidade ao acervo.</p>
        </div>
        <div class="page-actions"><a href="{{ route('autores.create') }}" class="btn btn-primary">Cadastrar autor</a></div>
    </header>

    <section class="panel" aria-label="Busca de autores">
        <form action="{{ route('autores.index') }}" method="GET" class="filter-form">
            <div class="field">
                <label for="q">Buscar autor</label>
                <input id="q" name="q" type="search" class="form-control" value="{{ request('q') }}" placeholder="Nome ou nacionalidade" maxlength="255">
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-secondary">Buscar</button>
                @if(request()->filled('q'))<a href="{{ route('autores.index') }}" class="btn btn-ghost">Limpar</a>@endif
            </div>
        </form>
    </section>

    <section class="panel">
        <div class="toolbar"><p class="muted">{{ $autores->total() }} {{ $autores->total() === 1 ? 'autor encontrado' : 'autores encontrados' }}</p></div>
        @if($autores->isEmpty())
            <div class="empty-state">
                <h2>Nenhum autor encontrado</h2>
                <p>Cadastre um autor para vinculá-lo aos livros do acervo.</p>
                <a href="{{ route('autores.create') }}" class="btn btn-primary">Cadastrar autor</a>
            </div>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th scope="col">Autor</th><th scope="col">Nacionalidade</th><th scope="col">Livros no acervo</th><th scope="col" class="table-actions">Ações</th></tr></thead>
                    <tbody>
                        @foreach($autores as $autor)
                            <tr>
                                <td><a href="{{ route('autores.show', $autor) }}" class="text-link">{{ $autor->nome }}</a></td>
                                <td>{{ $autor->nacionalidade ?: 'Não informada' }}</td>
                                <td>{{ $autor->livros_count }} {{ $autor->livros_count === 1 ? 'livro' : 'livros' }}</td>
                                <td>
                                    <div class="row-actions">
                                        <a href="{{ route('autores.edit', $autor) }}" class="btn btn-secondary btn-sm">Editar</a>
                                        <form action="{{ route('autores.destroy', $autor) }}" method="POST" data-confirm="Remover o autor &quot;{{ $autor->nome }}&quot;? Autores com livros cadastrados não podem ser removidos.">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm">Remover</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $autores->appends(request()->query())->links() }}
        @endif
    </section>
@endsection
