@extends('layouts.app')

@section('title', 'Cadastrar categoria')

@section('content')
    <a href="{{ route('categorias.index') }}" class="back-link">← Voltar às categorias</a>
    <header class="page-heading"><div><div class="eyebrow">Catálogo</div><h1>Cadastrar categoria</h1><p>Defina uma classificação clara para facilitar a descoberta de livros.</p></div></header>
    <section class="panel form-panel"><form action="{{ route('categorias.store') }}" method="POST">@csrf @include('categorias._form', ['categoria' => null, 'submitLabel' => 'Cadastrar categoria'])</form></section>
@endsection
