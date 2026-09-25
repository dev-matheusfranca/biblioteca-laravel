@extends('layouts.app')
@section('title', 'Leitores')
@section('content')
<header class="page-heading"><div><div class="eyebrow">Gestão da biblioteca</div><h1>Leitores</h1><p>Gerencie as contas e o acesso ao portal do leitor.</p></div><a class="btn btn-primary" href="{{ route('leitores.create') }}">Cadastrar leitor</a></header>
<section class="panel"><form method="GET" action="{{ route('leitores.index') }}" class="filter-form">
    <div class="field"><label for="q">Nome ou e-mail</label><input id="q" name="q" type="search" class="form-control" value="{{ request('q') }}" maxlength="255"></div>
    <div class="field"><label for="status">Acesso</label><select id="status" name="status" class="form-control"><option value="">Todos</option><option value="ativos" @selected(request('status') === 'ativos')>Ativos</option><option value="inativos" @selected(request('status') === 'inativos')>Inativos</option></select></div>
    <div class="form-actions"><button class="btn btn-secondary" type="submit">Filtrar</button><a href="{{ route('leitores.index') }}" class="btn btn-ghost">Limpar</a></div>
</form></section>
<section class="panel"><div class="toolbar"><p class="muted">{{ $leitores->total() }} resultado(s) · {{ $totalLeitores }} leitor(es) cadastrado(s)</p></div>
    @if($leitores->isEmpty())<div class="empty-state"><h2>Nenhum leitor encontrado</h2><p>Ajuste os filtros ou cadastre uma nova conta.</p></div>
    @else<div class="table-wrap"><table class="data-table"><thead><tr><th scope="col">Leitor</th><th scope="col">Acesso</th><th scope="col">Ação</th></tr></thead><tbody>
        @foreach($leitores as $leitor)<tr><td>{{ $leitor->name }}<span class="muted">{{ $leitor->email }}</span></td><td><span class="badge {{ $leitor->isActive() ? 'badge-success' : 'badge-neutral' }}">{{ $leitor->isActive() ? 'Ativo' : 'Inativo' }}</span></td><td><a class="btn btn-secondary btn-sm" href="{{ route('leitores.edit', $leitor) }}">Editar <span class="sr-only">{{ $leitor->name }}</span></a></td></tr>@endforeach
    </tbody></table></div>{{ $leitores->links() }}@endif
</section>
@endsection
