@extends('layouts.app')
@section('title', 'Criar conta')
@section('content')
<div class="auth-layout">
    <section class="auth-intro">
        <span class="eyebrow">UM ESPAÇO PARA COMPARTILHAR</span>
        <h1>Boas histórias<br>começam aqui.</h1>
        <p>Crie sua conta para consultar o catálogo e acompanhar seus próprios empréstimos.</p>
        <div class="auth-benefits">
            <div class="auth-benefit">@include('partials.icon', ['name' => 'book']) Livros, autores e categorias organizados</div>
            <div class="auth-benefit">@include('partials.icon', ['name' => 'arrows']) Um histórico para cada leitura</div>
            <div class="auth-benefit">@include('partials.icon', ['name' => 'check']) Disponibilidade para decidir com clareza</div>
        </div>
    </section>
    <section class="auth-card" aria-labelledby="register-title">
        <h2 id="register-title">Criar sua conta</h2>
        <p>Preencha os campos abaixo para começar.</p>
        <form method="POST" action="{{ route('register.post') }}">
            @csrf
            <div class="field">
                <label for="nome">Nome</label>
                <input id="nome" type="text" name="nome" class="form-control" value="{{ old('nome') }}" placeholder="Como podemos chamar você?" autocomplete="name" maxlength="255" required @error('nome') aria-invalid="true" aria-describedby="nome-error" @enderror>
                @error('nome')<span id="nome-error" class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="field">
                <label for="email">E-mail</label>
                <input id="email" type="email" name="email" class="form-control" value="{{ old('email') }}" placeholder="seu@email.com" autocomplete="email" maxlength="255" required @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                @error('email')<span id="email-error" class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="field">
                <label for="password">Senha</label>
                <input id="password" type="password" name="password" class="form-control" autocomplete="new-password" minlength="8" required aria-describedby="password-hint @error('password') password-error @enderror" @error('password') aria-invalid="true" @enderror>
                <p class="password-hint" id="password-hint">Use pelo menos 8 caracteres.</p>
                @error('password')<span id="password-error" class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="field">
                <label for="password_confirmation">Confirmar senha</label>
                <input id="password_confirmation" type="password" name="password_confirmation" class="form-control" autocomplete="new-password" minlength="8" required>
            </div>
            <button class="btn btn-primary" type="submit">Criar conta @include('partials.icon', ['name' => 'arrow'])</button>
        </form>
        <p class="auth-switch">Já tem uma conta? <a href="{{ route('login') }}">Entrar</a></p>
    </section>
</div>
@endsection
