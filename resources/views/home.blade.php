@extends('layouts.app')
@section('title', 'Visão geral')
@section('content')
<div class="page-heading">
    <div><span class="eyebrow">ACERVO & CIRCULAÇÃO</span><h1>Visão geral</h1><p>Um olhar sobre os livros e as histórias que circulam por aqui.</p></div>
    @auth<a class="btn btn-primary" href="{{ route('locacoes.create') }}">@include('partials.icon', ['name' => 'plus']) Novo empréstimo</a>@endauth
</div>
<section class="welcome-banner" aria-labelledby="welcome-title">
    <div class="welcome-copy">
        <span class="eyebrow">BEM-VINDO À SUA BIBLIOTECA</span>
        <h2 id="welcome-title">Cada livro<br>no seu lugar<span>.</span></h2>
        <p>Organize seu acervo, conecte leitores e acompanhe cada nova leitura.</p>
        @auth
            <a class="btn btn-dark" href="{{ route('livros.index') }}">Explorar o acervo @include('partials.icon', ['name' => 'arrow'])</a>
        @else
            <a class="btn btn-dark" href="{{ route('login') }}">Entrar na biblioteca @include('partials.icon', ['name' => 'arrow'])</a>
        @endauth
    </div>
    <div class="shelf-art" aria-hidden="true">
        <span class="shelf-orbit"></span>
        <div class="shelf-books"><span class="spine spine-one"><i></i>LITERATURA</span><span class="spine spine-two">HISTÓRIAS<i></i></span><span class="spine spine-three"><i></i>CONHECIMENTO</span><span class="spine spine-four">NOVOS MUNDOS<i></i></span><span class="spine spine-five"><i></i>DESCOBERTAS</span></div>
        <span class="shelf-base"></span><span class="shelf-caption">BOAS HISTÓRIAS MERECEM CIRCULAR</span>
    </div>
</section>
<div class="stats-grid" aria-label="Resumo do acervo">
    <div class="stat-item"><span class="stat-icon lilac">@include('partials.icon', ['name' => 'book'])</span><div><span class="stat-label">Títulos no acervo</span><strong>{{ number_format($stats['titulos'], 0, ',', '.') }}</strong><small>{{ $stats['exemplares'] }} exemplares cadastrados</small></div></div>
    <div class="stat-item"><span class="stat-icon mint">@include('partials.icon', ['name' => 'check'])</span><div><span class="stat-label">Exemplares disponíveis</span><strong>{{ number_format($stats['disponiveis'], 0, ',', '.') }}</strong><small>Prontos para a próxima leitura</small></div></div>
    @auth
        <a class="stat-item" href="{{ route('locacoes.index', ['status' => 'ativa']) }}"><span class="stat-icon blue">@include('partials.icon', ['name' => 'arrows'])</span><div><span class="stat-label">Empréstimos em aberto</span><strong>{{ $stats['emprestimos'] }}</strong><small>Leituras em andamento</small></div></a>
        <a class="stat-item" href="{{ route('locacoes.index', ['status' => 'atrasada']) }}"><span class="stat-icon peach">@include('partials.icon', ['name' => 'clock'])</span><div><span class="stat-label">Devoluções em atraso</span><strong>{{ $stats['atrasados'] }}</strong><small>{{ $stats['atrasados'] ? 'Precisam da sua atenção' : 'Tudo dentro do prazo' }}</small></div></a>
    @else
        <div class="stat-item"><span class="stat-icon blue">@include('partials.icon', ['name' => 'pen'])</span><div><span class="stat-label">Autores</span><strong>{{ $stats['autores'] }}</strong><small>Vozes que compõem o acervo</small></div></div>
        <div class="stat-item"><span class="stat-icon peach">@include('partials.icon', ['name' => 'tag'])</span><div><span class="stat-label">Categorias</span><strong>{{ $stats['categorias'] }}</strong><small>Universos para explorar</small></div></div>
    @endauth
</div>
<div class="dashboard-grid">
    <section class="panel recent-books" aria-labelledby="recent-title">
        <div class="section-heading"><div><h2 id="recent-title">Novos no acervo</h2><p>As adições mais recentes da biblioteca.</p></div>@auth<a class="text-link" href="{{ route('livros.index') }}">Ver todos @include('partials.icon', ['name' => 'arrow'])</a>@endauth</div>
        <div class="book-list">
            @forelse($livros as $livro)
                <article class="book-row">
                    <div class="mini-book cover-{{ $loop->index % 4 }}" aria-hidden="true"><span>{{ mb_strtoupper(mb_substr($livro->titulo, 0, 1)) }}</span></div>
                    <div class="book-info"><span class="book-category">{{ $livro->categoria->nome ?? 'Sem categoria' }}</span><h3>@auth<a href="{{ route('livros.show', $livro) }}">{{ $livro->titulo }}</a>@else{{ $livro->titulo }}@endauth</h3><p>{{ $livro->autor->nome ?? 'Autor não informado' }}</p></div>
                    <span @class(['badge', 'badge-success' => $livro->status === 'ativo' && $livro->quantidade_disponivel > 0, 'badge-neutral' => $livro->status !== 'ativo' || $livro->quantidade_disponivel <= 0])>{{ $livro->status === 'ativo' && $livro->quantidade_disponivel > 0 ? 'Disponível' : 'Indisponível' }}</span>
                </article>
            @empty
                <div class="empty-state">@include('partials.icon', ['name' => 'book'])<h3>O primeiro capítulo começa aqui</h3><p>Cadastre autores e categorias e adicione seu primeiro livro.</p><a class="btn btn-primary" href="{{ auth()->check() ? route('livros.create') : route('login') }}">{{ auth()->check() ? 'Cadastrar primeiro livro' : 'Entrar para começar' }}</a></div>
            @endforelse
        </div>
    </section>
    <aside class="dashboard-aside">
        <section class="panel quick-panel"><div class="section-heading"><div><h2>Próximos passos</h2><p>Sua rotina, com menos cliques.</p></div></div>
            @auth
                <a class="quick-link" href="{{ route('livros.create') }}"><span class="quick-icon lilac">@include('partials.icon', ['name' => 'plus'])</span><span><strong>Cadastrar um livro</strong><small>Abra espaço para novas histórias</small></span>@include('partials.icon', ['name' => 'arrow'])</a>
                <a class="quick-link" href="{{ route('locacoes.create') }}"><span class="quick-icon blue">@include('partials.icon', ['name' => 'arrows'])</span><span><strong>Registrar empréstimo</strong><small>Conecte um livro a um leitor</small></span>@include('partials.icon', ['name' => 'arrow'])</a>
                <a class="quick-link" href="{{ route('locacoes.index', ['status' => 'ativa']) }}"><span class="quick-icon mint">@include('partials.icon', ['name' => 'check'])</span><span><strong>Receber uma devolução</strong><small>Consulte os empréstimos em aberto</small></span>@include('partials.icon', ['name' => 'arrow'])</a>
            @else
                <div class="guest-invite"><span class="quick-icon lilac">@include('partials.icon', ['name' => 'book'])</span><h3>Seu acervo em um só lugar</h3><p>Entre para organizar os livros e acompanhar os empréstimos da biblioteca.</p><a href="{{ route('login') }}" class="btn btn-primary">Acessar minha conta</a><a href="{{ route('register') }}" class="text-link">Ainda não tenho conta @include('partials.icon', ['name' => 'arrow'])</a></div>
            @endauth
        </section>
        @auth
            <section class="panel due-panel"><div class="section-heading"><div><h2>Próximas devoluções</h2><p>Prazos para acompanhar.</p></div></div>
                @forelse($devolucoes as $locacao)
                    <a class="due-row" href="{{ route('locacoes.show', $locacao) }}"><span><strong>{{ $locacao->livro->titulo ?? 'Livro não encontrado' }}</strong><small>{{ $locacao->usuario->name ?? 'Leitor não encontrado' }}</small></span><span @class(['badge', 'badge-warning' => \Illuminate\Support\Carbon::parse($locacao->data_devolucao)->lt(today()), 'badge-neutral' => !\Illuminate\Support\Carbon::parse($locacao->data_devolucao)->lt(today())])>{{ \Illuminate\Support\Carbon::parse($locacao->data_devolucao)->format('d/m') }}</span></a>
                @empty<p class="quiet-empty">Nenhum empréstimo em aberto por enquanto.</p>@endforelse
            </section>
        @else
            <div class="reading-note"><span class="eyebrow">FEITO PARA O DIA A DIA</span><p>Menos tempo procurando.<br>Mais tempo compartilhando.</p></div>
        @endauth
    </aside>
</div>
@endsection
