@extends('layouts.app')

@section('title', 'Empréstimo #' . $locacao->id)

@section('content')
    @php($situacao = $locacao->situacao_atual)
    @php($statusClasses = ['ativa' => 'badge-success', 'devolvida' => 'badge-neutral', 'atrasada' => 'badge-warning'])
    <a href="{{ route('locacoes.index') }}" class="back-link">← Voltar aos empréstimos</a>
    <header class="page-heading">
        <div><div class="eyebrow">Empréstimo #{{ $locacao->id }}</div><h1>{{ $locacao->livro->titulo ?? 'Livro removido' }}</h1><p>Retirado por {{ $locacao->usuario->name ?? 'Usuário removido' }}.</p></div>
        <div class="page-actions">@if($locacao->status !== 'devolvida')<form action="{{ route('locacoes.devolver', $locacao) }}" method="POST" data-confirm="Confirmar a devolução deste livro?">@csrf<button type="submit" class="btn btn-primary">Registrar devolução</button></form>@endif</div>
    </header>
    <section class="panel">
        <dl class="detail-grid">
            <div><dt>Pessoa responsável</dt><dd>{{ $locacao->usuario->name ?? 'Usuário removido' }}<span class="muted">{{ $locacao->usuario->email ?? '' }}</span></dd></div>
            <div><dt>Livro</dt><dd>@if($locacao->livro)<a href="{{ route('livros.show', $locacao->livro) }}" class="text-link">{{ $locacao->livro->titulo }}</a>@else Livro removido @endif</dd></div>
            <div><dt>Data da retirada</dt><dd>{{ \Illuminate\Support\Carbon::parse($locacao->data_locacao)->format('d/m/Y') }}</dd></div>
            <div><dt>Devolução prevista</dt><dd>{{ \Illuminate\Support\Carbon::parse($locacao->data_devolucao)->format('d/m/Y') }}</dd></div>
            <div><dt>Devolução realizada</dt><dd>{{ $locacao->data_devolvido ? \Illuminate\Support\Carbon::parse($locacao->data_devolvido)->format('d/m/Y') : 'Pendente' }}</dd></div>
            <div><dt>Situação</dt><dd><span class="badge {{ $statusClasses[$situacao] ?? 'badge-neutral' }}">{{ ucfirst($situacao) }}</span></dd></div>
        </dl>
    </section>
@endsection
