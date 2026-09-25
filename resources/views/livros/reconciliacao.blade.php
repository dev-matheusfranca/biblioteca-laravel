@extends('layouts.app')
@section('title', 'Conferir exemplares')
@section('content')
<a class="back-link" href="{{ route('livros.show', $livro) }}">← Voltar ao livro</a>
<header class="page-heading"><div><div class="eyebrow">Conferência de inventário</div><h1>{{ $livro->titulo }}</h1><p>Identifique as unidades físicas e vincule os empréstimos antes de liberar novas retiradas.</p></div></header>
<section class="panel"><div class="panel-body"><p>Estoque informado anteriormente: <strong>{{ $discrepancias['total_legacy'] }}</strong> unidades; disponíveis: <strong>{{ $discrepancias['disponiveis_legacy'] }}</strong>; empréstimos abertos: <strong>{{ $locacoesAbertas->count() }}</strong>.</p><p class="panel-description">Informe somente códigos conferidos no acervo. Enquanto a conferência não for concluída, o título permite devoluções e permanece indisponível para novas retiradas.</p></div></section>
<section class="panel form-panel"><form action="{{ route('livros.reconciliacao.store', $livro) }}" method="POST">
    @csrf
    @php($unidades = old('unidades', array_fill(0, max(1, min(100, $livro->quantidade_total)), ['codigo_patrimonial' => '', 'condicao' => 'circulacao', 'identificacao_fisica' => false])))
    <div id="inventory-rows" data-next-index="{{ count($unidades) }}">
    @foreach($unidades as $index => $unidade)
        @include('exemplares._unidade', ['index' => $index, 'unidade' => $unidade])
    @endforeach
    </div>
    <button class="btn btn-secondary" type="button" data-add-unit>Adicionar unidade</button>
    @if($locacoesAbertas->isNotEmpty())
    <h2 class="panel-description">Unidades que estão emprestadas</h2>
    <p>Repita o código patrimonial da unidade entregue em cada empréstimo. Todas as unidades vinculadas precisam estar identificadas.</p>
    @foreach($locacoesAbertas as $locacao)
        <div class="field"><label for="vinculo-{{ $locacao->id }}">Empréstimo #{{ $locacao->id }} · {{ $locacao->usuario->name ?? 'Leitor' }}</label><input class="form-control" id="vinculo-{{ $locacao->id }}" name="vinculacoes[{{ $locacao->id }}]" value="{{ old('vinculacoes.'.$locacao->id) }}" maxlength="64" required placeholder="Código da unidade emprestada"></div>
    @endforeach
    @endif
    <div class="field panel-description"><label for="motivo">Registro da conferência</label><textarea class="form-control" name="motivo" id="motivo" required maxlength="1000" rows="3">{{ old('motivo') }}</textarea></div>
    <label class="checkbox-field"><input type="checkbox" name="confirmar_divergencia" value="1" @checked(old('confirmar_divergencia'))> Confirmo a correção dos saldos anteriores conforme a conferência física registrada acima.</label>
    <div class="form-actions"><button class="btn btn-primary" type="submit">Confirmar inventário e liberar circulação</button></div>
</form></section>
<template id="inventory-row-template">@include('exemplares._unidade', ['index' => '__INDEX__', 'unidade' => []])</template>
<script src="{{ asset('js/inventory.js') }}" defer></script>
@endsection
