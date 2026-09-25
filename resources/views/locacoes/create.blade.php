@extends('layouts.app')

@section('title', 'Registrar empréstimo')

@section('content')
    <a href="{{ route('locacoes.index') }}" class="back-link">← Voltar aos empréstimos</a>
    <header class="page-heading"><div><div class="eyebrow">Circulação</div><h1>Registrar empréstimo</h1><p>Escolha o leitor e o título. O atendimento confirma a disponibilidade e respeita as reservas da fila.</p></div></header>
    <section class="panel form-panel">
        <form action="{{ route('locacoes.store') }}" method="POST">
            @csrf
            <div class="form-grid">
                <div class="field field-wide">
                    <label for="usuario_id">Pessoa responsável</label>
                    <select id="usuario_id" name="usuario_id" class="form-control" required>
                        <option value="">Selecione uma pessoa</option>
                        @foreach($usuarios as $usuario)
                            <option value="{{ $usuario->id }}" @selected((string) old('usuario_id', request('usuario_id')) === (string) $usuario->id)>{{ $usuario->name }} · {{ $usuario->email }}</option>
                        @endforeach
                    </select>
                    @error('usuario_id')<p class="field-error">{{ $message }}</p>@enderror
                    @error('usuario')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div class="field field-wide">
                    <label for="livro_id">Livro</label>
                    <select id="livro_id" name="livro_id" class="form-control" required>
                        <option value="">Selecione um título</option>
                        @foreach($livros as $livro)
                            <option value="{{ $livro->id }}" @selected((string) old('livro_id', request('livro_id')) === (string) $livro->id)>{{ $livro->titulo }} · {{ $livro->quantidade_disponivel }} livre(s) · {{ $livro->reservas_ativas_count }} reserva(s)</option>
                        @endforeach
                    </select>
                    @error('livro_id')<p class="field-error">{{ $message }}</p>@enderror
                    @error('livro')<p class="field-error">{{ $message }}</p>@enderror
                    <p class="panel-description">Unidades separadas para reserva podem ser retiradas apenas pelo leitor correspondente.</p>
                </div>
                <div class="field">
                    <span>Prazo previsto</span>
                    <strong>{{ now($policy->timezone)->addDays($policy->loan_days)->format('d/m/Y') }}</strong>
                    <p class="panel-description">{{ $policy->loan_days }} dias corridos, até {{ $policy->max_open_loans }} empréstimos abertos por leitor. O prazo é confirmado no registro.</p>
                </div>
            </div>
            @if($livros->isEmpty())<p class="field-error">Não há livros disponíveis para empréstimo no momento.</p>@endif
            <div class="form-actions"><button type="submit" class="btn btn-primary" @disabled($livros->isEmpty() || $usuarios->isEmpty())>Registrar empréstimo</button><a href="{{ route('locacoes.index') }}" class="btn btn-ghost">Cancelar</a></div>
        </form>
    </section>
@endsection
