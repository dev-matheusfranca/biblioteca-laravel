@extends('layouts.app')
@section('title', 'Exemplares')
@section('content')
<a class="back-link" href="{{ route('livros.show', $livro) }}">← Voltar ao livro</a>
<header class="page-heading"><div><div class="eyebrow">Inventário físico</div><h1>{{ $livro->titulo }}</h1><p>Controle a condição e a identificação de cada unidade do acervo.</p></div></header>
@if(!$livro->usaExemplares())
<section class="panel"><div class="panel-body"><p>Este título aguarda a conferência do inventário.</p><a class="btn btn-primary" href="{{ route('livros.reconciliacao.edit', $livro) }}">Conferir exemplares</a></div></section>
@else
<section class="panel form-panel"><h2>Adicionar exemplar</h2><form method="POST" action="{{ route('exemplares.store', $livro) }}">@csrf
    <div class="field"><label for="codigo">Código patrimonial conferido</label><input class="form-control" id="codigo" name="codigo_patrimonial" maxlength="64" value="{{ old('codigo_patrimonial') }}" required></div>
    <div class="field"><label for="condicao">Condição inicial</label><select id="condicao" name="condicao" class="form-control"><option value="circulacao">Em circulação</option><option value="manutencao">Em manutenção</option></select></div>
    <div class="field"><label for="motivo">Origem ou motivo da inclusão</label><textarea class="form-control" id="motivo" name="motivo" required maxlength="1000" rows="2">{{ old('motivo') }}</textarea></div>
    <div class="form-actions"><button class="btn btn-primary" type="submit">Adicionar exemplar</button></div>
</form></section>
@endif
<section class="panel"><div class="panel-body">
    @forelse($exemplares as $exemplar)
    <form class="inventory-unit" method="POST" action="{{ route('exemplares.condicao.update', $exemplar) }}">@csrf @method('PATCH')
        <h2>{{ $exemplar->codigo_patrimonial }}</h2>
        <div class="field"><label for="condicao-{{ $exemplar->id }}">Condição de {{ $exemplar->codigo_patrimonial }}</label><select id="condicao-{{ $exemplar->id }}" name="condicao" class="form-control">@foreach(['circulacao'=>'Em circulação','manutencao'=>'Em manutenção','extraviado'=>'Extraviado','baixado'=>'Baixado'] as $value=>$label)<option value="{{ $value }}" @selected($exemplar->condicao->value === $value)>{{ $label }}</option>@endforeach</select></div>
        <div class="field"><label for="motivo-{{ $exemplar->id }}">Motivo da alteração</label><input id="motivo-{{ $exemplar->id }}" name="motivo" class="form-control" maxlength="1000" required></div>
        <button class="btn btn-secondary" type="submit">Atualizar condição</button>
    </form>
    @empty<div class="empty-state"><h2>Nenhum exemplar identificado</h2><p>Conclua a conferência para identificar o acervo.</p></div>@endforelse
</div>{{ $exemplares->links() }}</section>
@endsection
