@extends('layouts.app')
@section('title', $livro->titulo)
@section('content')
<a href="{{ route('catalogo.index') }}" class="back-link">← Voltar ao catálogo</a>
<header class="page-heading"><div><div class="eyebrow">Catálogo público</div><h1>{{ $livro->titulo }}</h1><p>{{ $livro->autor->nome ?? 'Autor não informado' }}</p></div></header>
<section class="panel">
    <dl class="detail-grid">
        <div><dt>Disponibilidade</dt><dd><span class="badge {{ ($livro->usaExemplares() && $livro->quantidade_disponivel > 0) ? 'badge-success' : 'badge-warning' }}">{{ ($livro->usaExemplares() && $livro->quantidade_disponivel > 0) ? 'Disponível' : 'Indisponível no momento' }}</span></dd></div>
        <div><dt>Categoria</dt><dd>{{ $livro->categoria->nome ?? 'Sem categoria' }}</dd></div>
        <div><dt>ISBN</dt><dd>{{ $livro->isbn ?: 'Não informado' }}</dd></div>
        <div><dt>Ano de publicação</dt><dd>{{ $livro->ano_publicacao ?: 'Não informado' }}</dd></div>
    </dl>
    <div class="panel-body"><p>Para retirar um livro, procure a equipe da biblioteca. A disponibilidade será confirmada no atendimento.</p>
    @if($reservaAtual)
        <p>Você já tem uma reserva deste título. Acompanhe o prazo e a disponibilidade na sua conta.</p>
    @elseif($podeReservar)
        <form method="POST" action="{{ route('reservas.store', $livro) }}">@csrf<p>Ao reservar, você entra na fila por ordem de solicitação. Quando houver uma unidade separada, o prazo de retirada aparecerá na sua conta.</p><button class="btn btn-primary" type="submit">Reservar este título</button></form>
    @elseif($motivoReserva)
        <p class="panel-description">{{ $motivoReserva }}</p>
    @endif
    @auth<a class="btn btn-secondary" href="{{ route('portal.index') }}">Ver meus empréstimos</a>@else<a class="btn btn-primary" href="{{ route('register') }}">Criar minha conta de leitor</a>@endauth
    </div>
</section>
@endsection
