@extends('layouts.app')
@section('title', 'Definir nova senha')
@section('content')
<div class="auth-layout"><section class="auth-intro"><span class="eyebrow">Acesso à sua conta</span><h1>Seu próximo<br>capítulo.</h1><p>Escolha uma senha com pelo menos 8 caracteres.</p></section>
<section class="auth-card"><h2>Definir nova senha</h2><form method="POST" action="{{ route('password.update') }}">@csrf<input type="hidden" name="token" value="{{ $token }}">
    <div class="field"><label for="email">E-mail</label><input id="email" name="email" type="email" class="form-control" value="{{ old('email', $email) }}" autocomplete="email" maxlength="255" required></div>
    <div class="field"><label for="password">Nova senha</label><input id="password" name="password" type="password" class="form-control" autocomplete="new-password" minlength="8" required @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>@error('password')<span id="password-error" class="field-error">{{ $message }}</span>@enderror</div>
    <div class="field"><label for="password_confirmation">Confirmar nova senha</label><input id="password_confirmation" name="password_confirmation" type="password" class="form-control" autocomplete="new-password" minlength="8" required></div>
    <button class="btn btn-primary" type="submit">Salvar nova senha</button>
</form></section></div>
@endsection
