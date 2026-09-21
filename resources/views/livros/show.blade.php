@extends('layouts.app')

@section('title', $livro->titulo)

@section('content')
    @php($disponivel = $livro->quantidade_disponivel > 0)
    @php($podeEmprestar = $livro->status === 'ativo' && $disponivel)
    <a href="{{ route('livros.index') }}" class="back-link">← Voltar ao acervo</a>
    <header class="page-heading">
        <div>
            <div class="eyebrow">Detalhes do acervo</div>
            <h1>{{ $livro->titulo }}</h1>
            <p>{{ $livro->autor->nome ?? 'Autor não informado' }} · {{ $livro->categoria->nome ?? 'Sem categoria' }}</p>
        </div>
        <div class="page-actions">
            @if($podeEmprestar)
                <a href="{{ route('locacoes.create', ['livro_id' => $livro->id]) }}" class="btn btn-primary">Registrar empréstimo</a>
            @elseif($livro->status !== 'ativo')
                <button type="button" class="btn btn-primary" disabled title="Ative o cadastro do livro antes de registrar um empréstimo.">Livro inativo para empréstimo</button>
            @else
                <button type="button" class="btn btn-primary" disabled title="Não há exemplares disponíveis no momento.">Sem exemplares disponíveis</button>
            @endif
            <a href="{{ route('livros.edit', $livro) }}" class="btn btn-secondary">Editar</a>
        </div>
    </header>

    <section class="panel">
        <dl class="detail-grid">
            <div><dt>Disponibilidade</dt><dd><span class="badge {{ $podeEmprestar ? 'badge-success' : ($livro->status === 'ativo' ? 'badge-warning' : 'badge-neutral') }}">{{ $podeEmprestar ? 'Disponível' : ($livro->status === 'ativo' ? 'Indisponível' : 'Cadastro inativo') }}</span> {{ $livro->status === 'ativo' ? $livro->quantidade_disponivel . ' de ' . $livro->quantidade_total . ' exemplar(es)' : 'Ative o cadastro para liberar novos empréstimos.' }}</dd></div>
            <div><dt>ISBN</dt><dd>{{ $livro->isbn ?: 'Não informado' }}</dd></div>
            <div><dt>Autor</dt><dd>{{ $livro->autor->nome ?? 'Não informado' }}</dd></div>
            <div><dt>Categoria</dt><dd>{{ $livro->categoria->nome ?? 'Não informada' }}</dd></div>
            <div><dt>Ano de publicação</dt><dd>{{ $livro->ano_publicacao ?: 'Não informado' }}</dd></div>
            <div><dt>Status do cadastro</dt><dd><span class="badge {{ $livro->status === 'ativo' ? 'badge-success' : 'badge-neutral' }}">{{ $livro->status === 'ativo' ? 'Ativo' : 'Inativo' }}</span></dd></div>
        </dl>
    </section>

    <section class="panel">
        <div class="toolbar">
            <div>
                <div class="eyebrow">Circulação</div>
                <h2>Histórico de empréstimos</h2>
            </div>
        </div>
        @if($livro->locacoes->isEmpty())
            <div class="empty-state">
                <h2>Este livro ainda não foi emprestado</h2>
                <p>O histórico de circulação aparecerá aqui após o primeiro empréstimo.</p>
                @if($podeEmprestar)<a href="{{ route('locacoes.create', ['livro_id' => $livro->id]) }}" class="btn btn-primary">Registrar empréstimo</a>@endif
            </div>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th scope="col">Pessoa</th><th scope="col">Retirada</th><th scope="col">Previsão de devolução</th><th scope="col">Situação</th></tr></thead>
                    <tbody>
                        @foreach($livro->locacoes as $locacao)
                            <tr>
                                <td><a href="{{ route('locacoes.show', $locacao) }}" class="text-link">{{ $locacao->usuario->name ?? 'Usuário removido' }}</a></td>
                                <td>{{ \Illuminate\Support\Carbon::parse($locacao->data_locacao)->format('d/m/Y') }}</td>
                                <td>{{ \Illuminate\Support\Carbon::parse($locacao->data_devolucao)->format('d/m/Y') }}</td>
                                @php($situacao = $locacao->situacao_atual)
                                <td><span class="badge {{ $situacao === 'devolvida' ? 'badge-neutral' : ($situacao === 'atrasada' ? 'badge-warning' : 'badge-success') }}">{{ ucfirst($situacao) }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
