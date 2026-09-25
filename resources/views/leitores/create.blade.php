@extends('layouts.app')
@section('title', 'Cadastrar leitor')
@section('content')
<header class="page-heading"><div><h1>Cadastrar leitor</h1><p>O leitor acessa somente sua conta pessoal.</p></div></header>
<section class="panel form-panel"><form method="POST" action="{{ route('leitores.store') }}">@include('leitores._form')</form></section>
@endsection
