@extends('layouts.app')

@section('title', $categoria->nome)

@section('content')
    <a href="{{ route('categorias.index') }}" class="back-link">← Voltar às categorias</a>
    <header class="page-heading"><div><div class="eyebrow">Categoria</div><h1>{{ $categoria->nome }}</h1><p>{{ $categoria->descricao ?: 'Sem descrição cadastrada.' }}</p></div><div class="page-actions"><a href="{{ route('categorias.edit', $categoria) }}" class="btn btn-secondary">Editar</a></div></header>
    <section class="panel">
        <div class="toolbar"><div><div class="eyebrow">Acervo</div><h2>Livros nesta categoria</h2></div></div>
        @if($livros->isEmpty())
            <div class="empty-state"><h2>Nenhum livro nesta categoria</h2><p>Vincule esta categoria ao cadastrar ou editar um livro.</p><a href="{{ route('livros.create') }}" class="btn btn-primary">Cadastrar livro</a></div>
        @else
            <div class="table-wrap"><table class="data-table"><thead><tr><th scope="col">Título</th><th scope="col">Autor</th><th scope="col">Disponibilidade</th></tr></thead><tbody>
                @foreach($livros as $livro)
                    <tr><td><a href="{{ route('livros.show', $livro) }}" class="text-link">{{ $livro->titulo }}</a></td><td>{{ $livro->autor->nome ?? 'Autor não informado' }}</td><td>@include('livros._availability')</td></tr>
                @endforeach
            </tbody></table></div>
            {{ $livros->links() }}
        @endif
    </section>
@endsection
