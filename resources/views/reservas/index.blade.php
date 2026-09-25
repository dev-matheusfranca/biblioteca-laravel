@extends('layouts.app')
@section('title', 'Reservas')
@section('content')
<header class="page-heading"><div><div class="eyebrow">Circulação</div><h1>Reservas</h1><p>Acompanhe a fila e atenda as unidades separadas dentro do prazo de retirada.</p></div></header>
<section class="panel"><div class="toolbar"><h2>Fila e histórico</h2><p class="muted">{{ $reservas->total() }} reserva(s)</p></div>@include('reservas._list', ['staff' => true])</section>
@endsection
