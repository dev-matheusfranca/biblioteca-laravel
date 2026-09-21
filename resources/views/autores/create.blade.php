@extends('layouts.app')

@section('title', 'Cadastrar autor')

@section('content')
    <a href="{{ route('autores.index') }}" class="back-link">← Voltar aos autores</a>
    <header class="page-heading"><div><div class="eyebrow">Catálogo</div><h1>Cadastrar autor</h1><p>Registre a autoria antes de incluir novos títulos no acervo.</p></div></header>
    <section class="panel form-panel"><form action="{{ route('autores.store') }}" method="POST">@csrf @include('autores._form', ['autor' => null, 'submitLabel' => 'Cadastrar autor'])</form></section>
@endsection
