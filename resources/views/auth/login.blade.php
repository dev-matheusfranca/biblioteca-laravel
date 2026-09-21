@extends('layouts.app')
@section('title', 'Entrar')
@section('content')
<div class="auth-layout">
    <section class="auth-intro">
        <span class="eyebrow">BEM-VINDO DE VOLTA</span>
        <h1>Sua biblioteca.<br>Seu próximo capítulo.</h1>
        <p>Acesse sua conta para organizar o acervo e acompanhar as leituras em andamento.</p>
        <div class="auth-benefits">
            <div class="auth-benefit">@include('partials.icon', ['name' => 'book']) Todo o acervo em um só lugar</div>
            <div class="auth-benefit">@include('partials.icon', ['name' => 'arrows']) Empréstimos fáceis de acompanhar</div>
            <div class="auth-benefit">@include('partials.icon', ['name' => 'clock']) Prazos de devolução sempre à vista</div>
        </div>
    </section>
    <section class="auth-card" aria-labelledby="login-title">
        <h2 id="login-title">Entrar na biblioteca</h2>
        <p>Informe seus dados para continuar.</p>
        <form method="POST" action="{{ route('login.post') }}">
            @csrf
            <div class="field">
                <label for="email">E-mail</label>
                <input id="email" type="email" name="email" class="form-control" value="{{ old('email') }}" placeholder="seu@email.com" autocomplete="username" maxlength="255" required @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                @error('email')<span id="email-error" class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="field">
                <label for="password">Senha</label>
                <input id="password" type="password" name="password" class="form-control" placeholder="Sua senha" autocomplete="current-password" required @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                @error('password')<span id="password-error" class="field-error">{{ $message }}</span>@enderror
            </div>
            <label class="checkbox-field" for="remember"><input type="checkbox" name="remember" id="remember" value="1" @checked(old('remember'))> Manter conectado neste dispositivo</label>
            <button class="btn btn-primary" type="submit">Entrar @include('partials.icon', ['name' => 'arrow'])</button>
        </form>
        <p class="auth-switch">Ainda não tem uma conta? <a href="{{ route('register') }}">Criar conta</a></p>
    </section>
</div>
@endsection
