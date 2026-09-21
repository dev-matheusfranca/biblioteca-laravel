@extends('layouts.app')

@section('title', $autor->nome)

@section('content')
    <a href="{{ route('autores.index') }}" class="back-link">← Voltar aos autores</a>
    <header class="page-heading">
        <div><div class="eyebrow">Autor</div><h1>{{ $autor->nome }}</h1><p>{{ $autor->nacionalidade ?: 'Nacionalidade não informada' }}</p></div>
        <div class="page-actions"><a href="{{ route('autores.edit', $autor) }}" class="btn btn-secondary">Editar</a></div>
    </header>
    <section class="panel">
        <div class="toolbar"><div><div class="eyebrow">Acervo</div><h2>Livros vinculados</h2></div></div>
        @if($autor->livros->isEmpty())
            <div class="empty-state"><h2>Nenhum livro vinculado</h2><p>Quando um livro deste autor for cadastrado, ele aparecerá nesta lista.</p><a href="{{ route('livros.create') }}" class="btn btn-primary">Cadastrar livro</a></div>
        @else
            <div class="table-wrap"><table class="data-table"><thead><tr><th scope="col">Título</th><th scope="col">Categoria</th><th scope="col">Disponibilidade</th></tr></thead><tbody>
                @foreach($autor->livros as $livro)
                    <tr><td><a href="{{ route('livros.show', $livro) }}" class="text-link">{{ $livro->titulo }}</a></td><td>{{ $livro->categoria->nome ?? 'Sem categoria' }}</td><td>@include('livros._availability')</td></tr>
                @endforeach
            </tbody></table></div>
        @endif
    </section>
@endsection
