@extends('layouts.app')

@section('title', 'Editar autor')

@section('content')
    <a href="{{ route('autores.show', $autor) }}" class="back-link">← Voltar aos detalhes</a>
    <header class="page-heading"><div><div class="eyebrow">Catálogo</div><h1>Editar autor</h1><p>Atualize os dados de <strong>{{ $autor->nome }}</strong>.</p></div></header>
    <section class="panel form-panel"><form action="{{ route('autores.update', $autor) }}" method="POST">@csrf @method('PUT') @include('autores._form', ['autor' => $autor, 'submitLabel' => 'Salvar alterações'])</form></section>
@endsection
