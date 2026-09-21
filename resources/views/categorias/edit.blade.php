@extends('layouts.app')

@section('title', 'Editar categoria')

@section('content')
    <a href="{{ route('categorias.show', $categoria) }}" class="back-link">← Voltar aos detalhes</a>
    <header class="page-heading"><div><div class="eyebrow">Catálogo</div><h1>Editar categoria</h1><p>Atualize a classificação <strong>{{ $categoria->nome }}</strong>.</p></div></header>
    <section class="panel form-panel"><form action="{{ route('categorias.update', $categoria) }}" method="POST">@csrf @method('PUT') @include('categorias._form', ['categoria' => $categoria, 'submitLabel' => 'Salvar alterações'])</form></section>
@endsection
