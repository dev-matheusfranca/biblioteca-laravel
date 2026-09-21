@extends('layouts.app')

@section('title', 'Editar livro')

@section('content')
    <a href="{{ route('livros.show', $livro) }}" class="back-link">← Voltar aos detalhes</a>
    <header class="page-heading">
        <div>
            <div class="eyebrow">Acervo</div>
            <h1>Editar livro</h1>
            <p>Atualize os dados de <strong>{{ $livro->titulo }}</strong>.</p>
        </div>
    </header>

    <section class="panel form-panel">
        <form action="{{ route('livros.update', $livro) }}" method="POST">
            @csrf
            @method('PUT')
            @include('livros._form', ['livro' => $livro, 'submitLabel' => 'Salvar alterações'])
        </form>
    </section>
@endsection
