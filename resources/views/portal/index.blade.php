@extends('layouts.app')
@section('title', 'Minha conta')
@section('content')
<header class="page-heading"><div><div class="eyebrow">Portal do leitor</div><h1>Minhas leituras</h1><p>Acompanhe seus empréstimos e os prazos de devolução.</p></div><a class="btn btn-primary" href="{{ route('catalogo.index') }}">Explorar o catálogo</a></header>
<section class="panel"><div class="panel-body"><h2>{{ $abertas }} empréstimo(s) em aberto</h2><p class="panel-description">Retiradas e devoluções são registradas pela equipe da biblioteca.</p></div></section>
<section class="panel"><div class="toolbar"><h2>Minhas reservas</h2><p class="muted">{{ $reservas->total() }} reserva(s)</p></div>@include('reservas._list', ['staff' => false])</section>
<section class="panel"><div class="toolbar"><h2>Avisos recentes</h2><a class="text-link" href="{{ route('avisos.index') }}">Ver todos os avisos</a></div><div class="panel-body reservation-list">@include('portal._avisos')</div></section>
<section class="panel">
    <div class="toolbar"><h2>Meu histórico</h2><p class="muted">{{ $locacoes->total() }} empréstimo(s)</p></div>
    @if($locacoes->isEmpty())
        <div class="empty-state"><h2>Sua próxima leitura começa aqui</h2><p>Quando você retirar um livro, o empréstimo e o prazo aparecerão nesta página.</p><a href="{{ route('catalogo.index') }}" class="btn btn-secondary">Consultar livros</a></div>
    @else
        <div class="table-wrap"><table class="data-table"><thead><tr><th scope="col">Livro</th><th scope="col">Retirada</th><th scope="col">Devolução prevista</th><th scope="col">Situação</th></tr></thead><tbody>
        @foreach($locacoes as $locacao)
            @php($situacao = $locacao->situacao_atual)
            <tr><td><a href="{{ route('portal.emprestimos.show', $locacao) }}" class="text-link">{{ $locacao->livro->titulo ?? 'Livro indisponível' }}</a></td><td>{{ \Illuminate\Support\Carbon::parse($locacao->data_locacao)->format('d/m/Y') }}</td><td>{{ \Illuminate\Support\Carbon::parse($locacao->data_devolucao)->format('d/m/Y') }}</td><td><span class="badge {{ in_array($situacao, ['devolvida', 'perdida']) ? 'badge-neutral' : ($situacao === 'atrasada' ? 'badge-warning' : 'badge-success') }}">{{ ucfirst($situacao) }}</span></td></tr>
        @endforeach
        </tbody></table></div>
        {{ $locacoes->links() }}
    @endif
</section>
@endsection
