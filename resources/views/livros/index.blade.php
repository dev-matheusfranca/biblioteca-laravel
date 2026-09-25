@extends('layouts.app')

@section('title', 'Acervo')

@section('content')
    <header class="page-heading">
        <div>
            <div class="eyebrow">Acervo</div>
            <h1>Livros</h1>
            <p>Consulte a disponibilidade e mantenha o catálogo organizado.</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('isbn.index') }}" class="btn btn-secondary">Consultar ISBN</a>
            <a href="{{ route('livros.create') }}" class="btn btn-primary">Cadastrar livro</a>
        </div>
    </header>

    <section class="panel" aria-label="Filtros do acervo">
        <form action="{{ route('livros.index') }}" method="GET" class="filter-form">
            <div class="field">
                <label for="q">Buscar no acervo</label>
                <input id="q" name="q" type="search" class="form-control" value="{{ request('q') }}" placeholder="Título, autor ou ISBN" maxlength="255">
            </div>
            <div class="field">
                <label for="categoria">Categoria</label>
                <select id="categoria" name="categoria" class="form-control">
                    <option value="">Todas as categorias</option>
                    @foreach($categorias as $categoria)
                        <option value="{{ $categoria->id }}" @selected((string) request('categoria') === (string) $categoria->id)>{{ $categoria->nome }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="disponibilidade">Disponibilidade</label>
                <select id="disponibilidade" name="disponibilidade" class="form-control">
                    <option value="">Todas as situações</option>
                    <option value="disponivel" @selected(request('disponibilidade') === 'disponivel')>Com exemplares disponíveis</option>
                    <option value="indisponivel" @selected(request('disponibilidade') === 'indisponivel')>Sem exemplares disponíveis</option>
                </select>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-secondary">Filtrar</button>
                @if(request()->hasAny(['q', 'categoria', 'disponibilidade']))
                    <a href="{{ route('livros.index') }}" class="btn btn-ghost">Limpar</a>
                @endif
            </div>
        </form>
    </section>

    <section class="panel">
        <div class="toolbar">
            <p class="muted">{{ $livros->total() }} {{ $livros->total() === 1 ? 'livro encontrado' : 'livros encontrados' }}</p>
        </div>

        @if($livros->isEmpty())
            <div class="empty-state">
                <h2>Nenhum livro encontrado</h2>
                <p>Ajuste os filtros ou adicione o primeiro título ao acervo.</p>
                <a href="{{ route('livros.create') }}" class="btn btn-primary">Cadastrar livro</a>
            </div>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col">Livro</th>
                            <th scope="col">Categoria</th>
                            <th scope="col">Disponibilidade</th>
                            <th scope="col" class="table-actions">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($livros as $livro)
                            @php($disponivel = ($livro->usaExemplares() && $livro->quantidade_disponivel > 0))
                            @php($podeEmprestar = $livro->status === 'ativo' && $disponivel)
                            <tr>
                                <td>
                                    <a class="text-link" href="{{ route('livros.show', $livro) }}">{{ $livro->titulo }}</a>
                                    <span class="muted">{{ $livro->autor->nome ?? 'Autor não informado' }}@if($livro->isbn) · ISBN {{ $livro->isbn }}@endif</span>
                                </td>
                                <td>{{ $livro->categoria->nome ?? 'Sem categoria' }}</td>
                                <td>
                                    <span class="badge {{ $podeEmprestar ? 'badge-success' : ($livro->status === 'ativo' ? 'badge-warning' : 'badge-neutral') }}">{{ $podeEmprestar ? 'Disponível' : ($livro->status === 'ativo' ? 'Indisponível' : 'Cadastro inativo') }}</span>
                                    <span class="muted">{{ $livro->status === 'ativo' ? $livro->quantidade_disponivel . ' de ' . $livro->quantidade_total . ' exemplar(es)' : 'Não disponível para empréstimos' }}</span>
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <a href="{{ route('livros.show', $livro) }}" class="btn btn-ghost btn-sm">Detalhes</a>
                                        <a href="{{ route('livros.edit', $livro) }}" class="btn btn-secondary btn-sm">Editar</a>
                                        <form action="{{ route('livros.destroy', $livro) }}" method="POST" data-confirm="Remover o livro &quot;{{ $livro->titulo }}&quot; do acervo? Esta ação não pode ser desfeita.">
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
            {{ $livros->appends(request()->query())->links() }}
        @endif
    </section>
@endsection
