@extends('layouts.app')
@section('title', 'Tokens de API')
@section('content')
<a class="back-link" href="{{ route('portal.index') }}">← Voltar à minha conta</a>
<header class="page-heading"><div><div class="eyebrow">Portal do leitor</div><h1>Tokens de API</h1><p>Crie credenciais pessoais com permissões específicas. Cada token expira em 24 horas.</p></div></header>

@if($newToken)
<section class="panel"><div class="panel-body"><h2>Copie o token agora</h2><p>Este segredo será exibido somente nesta resposta. Sair ou navegar para outra página o remove da tela.</p><label for="new-token">Token</label><input id="new-token" class="form-control" type="text" readonly value="{{ $newToken }}" autocomplete="off"></div></section>
@endif

<section class="panel"><div class="panel-body"><h2>Criar token</h2><form method="POST" action="{{ route('tokens.store') }}">@csrf
<div class="form-group"><label for="name">Nome do uso</label><input id="name" name="name" class="form-control" maxlength="100" required value="{{ old('name') }}" placeholder="Ex.: demonstração local"></div>
<fieldset><legend>Permissões</legend>@foreach($abilities as $ability => $label)<label class="check-option"><input type="checkbox" name="abilities[]" value="{{ $ability }}" @checked(in_array($ability, old('abilities', ['personal:read']), true))> <span>{{ $label }}</span></label>@endforeach</fieldset>
<div class="form-group"><label for="current_password">Senha atual</label><input id="current_password" name="current_password" class="form-control" type="password" required autocomplete="current-password"></div>
<button class="btn btn-primary" type="submit">Criar token por 24 horas</button></form></div></section>

<section class="panel"><div class="toolbar"><h2>Tokens ativos</h2><p class="muted">{{ $tokens->count() }} de 10</p></div><div class="panel-body">
@forelse($tokens as $token)
<article class="reservation-card"><h3>{{ $token->name }}</h3><p class="muted">Permissões: {{ collect($token->abilities)->map(fn($ability) => $abilities[$ability] ?? $ability)->join(', ') }}</p><p class="muted">Criado em {{ $token->created_at->format('d/m/Y H:i') }} · expira em {{ $token->expires_at?->format('d/m/Y H:i') }}</p><form method="POST" action="{{ route('tokens.destroy', $token->id) }}">@csrf @method('DELETE')<button class="btn btn-secondary btn-sm" type="submit">Revogar</button></form></article>
@empty
<div class="empty-state"><h3>Nenhum token ativo</h3><p>Crie um token para testar a API pessoal.</p></div>
@endforelse
</div></section>
@endsection
