<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#20243d">
    <meta name="description" content="Organize seu acervo e acompanhe empréstimos e devoluções em um só lugar.">
    <title>@yield('title', 'Visão geral') · Biblioteca</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('vendor/tom-select/tom-select.default.min.css') }}?v=2.6.2">
    <link rel="stylesheet" href="{{ asset('vendor/flatpickr/flatpickr.min.css') }}?v=4.6.13">
    <link rel="stylesheet" href="{{ asset('css/biblioteca.css') }}?v={{ filemtime(public_path('css/biblioteca.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/select-picker.css') }}?v={{ filemtime(public_path('css/select-picker.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/date-picker.css') }}?v={{ filemtime(public_path('css/date-picker.css')) }}">
    <script src="{{ asset('vendor/tom-select/tom-select.base.min.js') }}?v=2.6.2" defer></script>
    <script src="{{ asset('vendor/flatpickr/flatpickr.min.js') }}?v=4.6.13" defer></script>
    <script src="{{ asset('vendor/flatpickr/pt.js') }}?v=4.6.13" defer></script>
    <script src="{{ asset('js/select-picker.js') }}?v={{ filemtime(public_path('js/select-picker.js')) }}" defer></script>
    <script src="{{ asset('js/date-picker.js') }}?v={{ filemtime(public_path('js/date-picker.js')) }}" defer></script>
    <script src="{{ asset('js/biblioteca.js') }}?v={{ filemtime(public_path('js/biblioteca.js')) }}" defer></script>
</head>
<body>
    <a class="skip-link" href="#conteudo">Pular para o conteúdo</a>
    <div class="app-shell">
        <aside class="sidebar" id="navigation">
            <a class="brand" href="{{ route('home') }}" aria-label="Biblioteca, início">
                <span class="brand-symbol">@include('partials.icon', ['name' => 'book'])</span>
                <span>Biblioteca<small>ACERVO & CIRCULAÇÃO</small></span>
            </a>
            <div class="nav-label">ESPAÇO DA BIBLIOTECA</div>
            <nav class="primary-nav" aria-label="Navegação principal">
                <a href="{{ route('home') }}" @class(['nav-item', 'is-active' => request()->routeIs('home')]) @if(request()->routeIs('home')) aria-current="page" @endif>@include('partials.icon', ['name' => 'grid']) Visão geral</a>
                <a href="{{ route('catalogo.index') }}" @class(['nav-item', 'is-active' => request()->routeIs('catalogo.*')]) @if(request()->routeIs('catalogo.*')) aria-current="page" @endif>@include('partials.icon', ['name' => 'book']) Catálogo público</a>
                @auth
                    <a href="{{ route('portal.index') }}" @class(['nav-item', 'is-active' => request()->routeIs('portal.*')]) @if(request()->routeIs('portal.*')) aria-current="page" @endif>@include('partials.icon', ['name' => 'clock']) Minha conta</a>
                @endauth
                @if(auth()->user()?->isActive() && auth()->user()?->role === \App\Enums\UserRole::Reader)
                    <a href="{{ route('tokens.index') }}" @class(['nav-item', 'is-active' => request()->routeIs('tokens.*')])>@include('partials.icon', ['name' => 'grid']) Acesso à API</a>
                @endif
                @can('manage-library')
                    <a href="{{ route('relatorios.circulacao') }}" @class(['nav-item', 'is-active' => request()->routeIs('relatorios.*')])>@include('partials.icon', ['name' => 'grid']) Relatórios</a>
                    <a href="{{ route('livros.index') }}" @class(['nav-item', 'is-active' => request()->routeIs('livros.*')]) @if(request()->routeIs('livros.*')) aria-current="page" @endif>@include('partials.icon', ['name' => 'book']) Acervo de livros</a>
                    <a href="{{ route('locacoes.index') }}" @class(['nav-item', 'is-active' => request()->routeIs('locacoes.*')]) @if(request()->routeIs('locacoes.*')) aria-current="page" @endif>@include('partials.icon', ['name' => 'arrows']) Empréstimos</a>
                    <a href="{{ route('reservas.index') }}" @class(['nav-item', 'is-active' => request()->routeIs('reservas.index')])>@include('partials.icon', ['name' => 'clock']) Reservas</a>
                    <span class="nav-label">ORGANIZAÇÃO</span>
                    <a href="{{ route('autores.index') }}" @class(['nav-item', 'is-active' => request()->routeIs('autores.*')]) @if(request()->routeIs('autores.*')) aria-current="page" @endif>@include('partials.icon', ['name' => 'pen']) Autores</a>
                    <a href="{{ route('categorias.index') }}" @class(['nav-item', 'is-active' => request()->routeIs('categorias.*')]) @if(request()->routeIs('categorias.*')) aria-current="page" @endif>@include('partials.icon', ['name' => 'tag']) Categorias</a>
                    <a href="{{ route('leitores.index') }}" @class(['nav-item', 'is-active' => request()->routeIs('leitores.*')]) @if(request()->routeIs('leitores.*')) aria-current="page" @endif>@include('partials.icon', ['name' => 'grid']) Leitores</a>
                @endcan
                @can('manage-users')
                    <a href="{{ route('operacao.saude') }}" @class(['nav-item', 'is-active' => request()->routeIs('operacao.saude')])>@include('partials.icon', ['name' => 'grid']) Saúde da operação</a>
                    <a href="{{ route('operacao.comunicacoes.index') }}" @class(['nav-item', 'is-active' => request()->routeIs('operacao.*')])>@include('partials.icon', ['name' => 'clock']) Comunicações</a>
                    <a href="{{ route('configuracoes.circulacao.edit') }}" @class(['nav-item', 'is-active' => request()->routeIs('configuracoes.*')])>@include('partials.icon', ['name' => 'grid']) Regras de circulação</a>
                    <a href="{{ route('equipe.index') }}" @class(['nav-item', 'is-active' => request()->routeIs('equipe.*')]) @if(request()->routeIs('equipe.*')) aria-current="page" @endif>@include('partials.icon', ['name' => 'grid']) Equipe e acessos</a>
                @endcan
            </nav>
            <div class="sidebar-note">
                @include('partials.icon', ['name' => 'spark'])
                <strong>Histórias que circulam.</strong>
                <p>Um espaço para cuidar dos livros e de quem lê.</p>
            </div>
            <div class="sidebar-footer"><span class="status-dot"></span> Seu acervo, conectado.</div>
        </aside>
        <div class="workspace">
            <header class="topbar">
                <button class="icon-button menu-toggle" type="button" aria-expanded="false" aria-controls="navigation" aria-label="Abrir menu">@include('partials.icon', ['name' => 'menu'])</button>
                <div class="breadcrumbs"><span>Biblioteca</span><span aria-hidden="true">/</span><strong>@yield('title', 'Visão geral')</strong></div>
                <div class="account-actions">
                    @auth
                        <span class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                        <span class="account-name">{{ auth()->user()->name }}</span>
                        <form action="{{ route('logout') }}" method="POST">@csrf<button class="btn btn-ghost btn-sm" type="submit">Sair</button></form>
                    @else
                        <a href="{{ route('login') }}" class="btn btn-ghost btn-sm">Entrar</a>
                        <a href="{{ route('register') }}" class="btn btn-primary btn-sm">Criar conta @include('partials.icon', ['name' => 'arrow'])</a>
                    @endauth
                </div>
            </header>
            <main id="conteudo" class="main-content" tabindex="-1">
                @include('partials.flash')
                @yield('content')
            </main>
            <footer class="app-footer"><span>Biblioteca</span><span>Organizar. Emprestar. Compartilhar.</span></footer>
        </div>
    </div>
</body>
</html>
