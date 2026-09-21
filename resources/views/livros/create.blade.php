@extends('layouts.app')

@section('title', 'Cadastrar livro')

@section('content')
    <a href="{{ route('livros.index') }}" class="back-link">← Voltar ao acervo</a>
    <header class="page-heading">
        <div>
            <div class="eyebrow">Acervo</div>
            <h1>Cadastrar livro</h1>
            <p>Inclua os dados do título e a quantidade inicial de exemplares.</p>
        </div>
    </header>

    <section class="panel form-panel">
        <form action="{{ route('livros.store') }}" method="POST">
            @csrf
            @include('livros._form', ['livro' => null, 'submitLabel' => 'Cadastrar livro'])
        </form>
    </section>
@endsection
