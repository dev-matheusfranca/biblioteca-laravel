@extends('layouts.app')
@section('title', 'Meus avisos')
@section('content')
<a class="back-link" href="{{ route('portal.index') }}">← Voltar à minha conta</a>
<header class="page-heading"><div><div class="eyebrow">Portal do leitor</div><h1>Meus avisos</h1><p>Acompanhe os prazos dos empréstimos e a disponibilidade das suas reservas.</p></div></header>
<section class="panel"><div class="panel-body reservation-list">@include('portal._avisos')</div>{{ $avisos->links() }}</section>
@endsection
