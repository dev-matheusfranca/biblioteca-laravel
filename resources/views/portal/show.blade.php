@extends('layouts.app')
@section('title', 'Meu empréstimo')
@section('content')
<a href="{{ route('portal.index') }}" class="back-link">← Voltar à minha conta</a>
<header class="page-heading"><div><div class="eyebrow">Empréstimo #{{ $locacao->id }}</div><h1>{{ $locacao->livro->titulo ?? 'Livro indisponível' }}</h1><p>Para esclarecimentos sobre este empréstimo, procure a equipe da biblioteca.</p></div></header>
<section class="panel"><dl class="detail-grid">
    <div><dt>Retirada</dt><dd>{{ \Illuminate\Support\Carbon::parse($locacao->data_locacao)->format('d/m/Y') }}</dd></div>
    <div><dt>Devolução prevista</dt><dd>{{ \Illuminate\Support\Carbon::parse($locacao->data_devolucao)->format('d/m/Y') }}</dd></div>
    <div><dt>Devolução realizada</dt><dd>{{ $locacao->data_devolvido ? \Illuminate\Support\Carbon::parse($locacao->data_devolvido)->format('d/m/Y') : ($locacao->encerramento_motivo === 'perda' ? 'Não realizada — encerrado por perda' : 'Pendente') }}</dd></div>
    <div><dt>Situação</dt><dd>{{ ucfirst($locacao->situacao_atual) }}</dd></div>
</dl></section>
@include('locacoes._renovacoes', ['staff' => false])
@endsection
