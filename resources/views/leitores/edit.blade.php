@extends('layouts.app')
@section('title', 'Editar leitor')
@section('content')
<header class="page-heading"><div><h1>Editar leitor</h1><p>Atualize os dados e a situação de acesso de {{ $leitor->name }}.</p></div></header>
<section class="panel form-panel"><form method="POST" action="{{ route('leitores.update', $leitor) }}">@method('PUT')@include('leitores._form')</form></section>
@endsection
