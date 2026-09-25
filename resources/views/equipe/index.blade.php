@extends('layouts.app')
@section('title', 'Equipe e acessos')
@section('content')
<header class="page-heading"><div><div class="eyebrow">Administração</div><h1>Equipe e acessos</h1><p>Defina o papel de cada conta. Alterações de permissão e acesso são registradas.</p></div></header>
<section class="panel"><div class="panel-body"><p><strong>Leitor:</strong> conta pessoal. <strong>Bibliotecário:</strong> gestão do acervo, circulação e leitores. <strong>Administrador:</strong> gestão e controle dos acessos.</p><p class="muted panel-description">Cadastre uma conta em Leitores antes de promovê-la à equipe. O próprio acesso não pode ser rebaixado ou inativado aqui.</p></div></section>
<section class="panel"><div class="panel-body">
    @forelse($usuarios as $usuario)
    <form class="team-member-form" method="POST" action="{{ route('equipe.update', $usuario) }}" data-confirm="Confirmar a alteração do acesso desta conta?">
        @csrf @method('PATCH')
        <div><h2>{{ $usuario->name }}</h2><p class="muted">{{ $usuario->email }}</p>@if($usuario->is(auth()->user()))<span class="badge badge-neutral">Sua conta</span>@endif</div>
        <div class="field"><label for="role-{{ $usuario->id }}">Papel de {{ $usuario->name }}</label><select class="form-control" id="role-{{ $usuario->id }}" name="role">@foreach($roles as $role)<option value="{{ $role->value }}" @selected($usuario->role === $role)>{{ $role->label() }}</option>@endforeach</select></div>
        <div><input type="hidden" name="is_active" value="0"><label class="checkbox-field" for="active-{{ $usuario->id }}"><input id="active-{{ $usuario->id }}" name="is_active" type="checkbox" value="1" @checked($usuario->isActive())> Acesso ativo <span class="sr-only">de {{ $usuario->name }}</span></label><button class="btn btn-secondary" type="submit">Salvar acesso <span class="sr-only">de {{ $usuario->name }}</span></button></div>
    </form>
    @empty<div class="empty-state"><h2>Nenhuma conta cadastrada</h2></div>@endforelse
    {{ $usuarios->links() }}
</div></section>
@endsection
