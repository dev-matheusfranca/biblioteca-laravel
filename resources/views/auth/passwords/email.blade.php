@extends('layouts.app')
@section('title', 'Recuperar senha')
@section('content')
<div class="auth-layout"><section class="auth-intro"><span class="eyebrow">Acesso à sua conta</span><h1>Vamos recuperar<br>seu acesso.</h1><p>Enviaremos um link para você escolher uma nova senha.</p></section>
<section class="auth-card"><h2>Recuperar senha</h2><form method="POST" action="{{ route('password.email') }}">@csrf
    <div class="field"><label for="email">E-mail da conta</label><input id="email" name="email" type="email" class="form-control" value="{{ old('email') }}" autocomplete="email" maxlength="255" required @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>@error('email')<span id="email-error" class="field-error">{{ $message }}</span>@enderror</div>
    <button class="btn btn-primary" type="submit">Enviar instruções</button>
</form><p class="auth-switch"><a href="{{ route('login') }}">Voltar ao login</a></p></section></div>
@endsection
