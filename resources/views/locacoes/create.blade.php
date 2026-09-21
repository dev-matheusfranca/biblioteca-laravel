@extends('layouts.app')

@section('title', 'Registrar empréstimo')

@section('content')
    <a href="{{ route('locacoes.index') }}" class="back-link">← Voltar aos empréstimos</a>
    <header class="page-heading"><div><div class="eyebrow">Circulação</div><h1>Registrar empréstimo</h1><p>Escolha a pessoa, o livro disponível e a data prevista para a devolução.</p></div></header>
    <section class="panel form-panel">
        <form action="{{ route('locacoes.store') }}" method="POST">
            @csrf
            <div class="form-grid">
                <div class="field field-wide">
                    <label for="usuario_id">Pessoa responsável</label>
                    <select id="usuario_id" name="usuario_id" class="form-control" required>
                        <option value="">Selecione uma pessoa</option>
                        @foreach($usuarios as $usuario)
                            <option value="{{ $usuario->id }}" @selected((string) old('usuario_id') === (string) $usuario->id)>{{ $usuario->name }} · {{ $usuario->email }}</option>
                        @endforeach
                    </select>
                    @error('usuario_id')<p class="field-error">{{ $message }}</p>@enderror
                    @error('usuario')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div class="field field-wide">
                    <label for="livro_id">Livro</label>
                    <select id="livro_id" name="livro_id" class="form-control" required>
                        <option value="">Selecione um livro disponível</option>
                        @foreach($livros as $livro)
                            <option value="{{ $livro->id }}" @selected((string) old('livro_id', request('livro_id')) === (string) $livro->id)>{{ $livro->titulo }} · {{ $livro->quantidade_disponivel }} disponível(is)</option>
                        @endforeach
                    </select>
                    @error('livro_id')<p class="field-error">{{ $message }}</p>@enderror
                    @error('livro')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="data_devolucao">Data prevista para devolução</label>
                    <input id="data_devolucao" name="data_devolucao" type="date" class="form-control" value="{{ old('data_devolucao') }}" min="{{ now()->addDay()->format('Y-m-d') }}" required>
                    @error('data_devolucao')<p class="field-error">{{ $message }}</p>@enderror
                </div>
            </div>
            @if($livros->isEmpty())<p class="field-error">Não há livros disponíveis para empréstimo no momento.</p>@endif
            <div class="form-actions"><button type="submit" class="btn btn-primary" @disabled($livros->isEmpty() || $usuarios->isEmpty())>Registrar empréstimo</button><a href="{{ route('locacoes.index') }}" class="btn btn-ghost">Cancelar</a></div>
        </form>
    </section>
@endsection
