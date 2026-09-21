@if($autores->isEmpty() || $categorias->isEmpty())
    <div class="alert alert-info" role="status">
        <strong>Prepare o catálogo antes de cadastrar um livro.</strong>
        <p>Você precisa de pelo menos um autor e uma categoria.</p>
        <div class="page-actions">
            @if($autores->isEmpty())<a class="text-link" href="{{ route('autores.create') }}">Cadastrar autor →</a>@endif
            @if($categorias->isEmpty())<a class="text-link" href="{{ route('categorias.create') }}">Cadastrar categoria →</a>@endif
        </div>
    </div>
@endif
<div class="form-grid">
    <div class="field field-wide">
        <label for="titulo">Título</label>
        <input id="titulo" name="titulo" type="text" class="form-control" value="{{ old('titulo', $livro?->titulo) }}" required maxlength="255" autocomplete="off">
        @error('titulo')<p class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="field">
        <label for="autor_id">Autor</label>
        <select id="autor_id" name="autor_id" class="form-control" required>
            <option value="">Selecione um autor</option>
            @foreach($autores as $autor)
                <option value="{{ $autor->id }}" @selected((string) old('autor_id', $livro?->autor_id) === (string) $autor->id)>{{ $autor->nome }}</option>
            @endforeach
        </select>
        @error('autor_id')<p class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="field">
        <label for="categoria_id">Categoria</label>
        <select id="categoria_id" name="categoria_id" class="form-control" required>
            <option value="">Selecione uma categoria</option>
            @foreach($categorias as $categoria)
                <option value="{{ $categoria->id }}" @selected((string) old('categoria_id', $livro?->categoria_id) === (string) $categoria->id)>{{ $categoria->nome }}</option>
            @endforeach
        </select>
        @error('categoria_id')<p class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="field">
        <label for="ano_publicacao">Ano de publicação</label>
        <input id="ano_publicacao" name="ano_publicacao" type="number" class="form-control" value="{{ old('ano_publicacao', $livro?->ano_publicacao) }}" min="1000" max="{{ now()->year }}" inputmode="numeric" placeholder="Ex.: 2024">
        @error('ano_publicacao')<p class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="field">
        <label for="quantidade_total">Quantidade total</label>
        <input id="quantidade_total" name="quantidade_total" type="number" class="form-control" value="{{ old('quantidade_total', $livro?->quantidade_total) }}" required min="0" inputmode="numeric">
        @error('quantidade_total')<p class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="field">
        <label for="isbn">ISBN <span class="muted">(opcional)</span></label>
        <input id="isbn" name="isbn" type="text" class="form-control" value="{{ old('isbn', $livro?->isbn) }}" maxlength="32" inputmode="numeric" autocomplete="off">
        @error('isbn')<p class="field-error">{{ $message }}</p>@enderror
    </div>
    <div class="field">
        <label for="status">Situação do cadastro</label>
        <select id="status" name="status" class="form-control" required aria-describedby="status-hint">
            <option value="ativo" @selected(old('status', $livro?->status ?? 'ativo') === 'ativo')>Ativo — permite empréstimos</option>
            <option value="inativo" @selected(old('status', $livro?->status ?? 'ativo') === 'inativo')>Inativo — suspende novos empréstimos</option>
        </select>
        <small id="status-hint">Inativar mantém o livro e o histórico. Empréstimos em aberto ainda podem ser devolvidos.</small>
        @error('status')<p class="field-error">{{ $message }}</p>@enderror
    </div>
</div>

<div class="form-actions">
    <button type="submit" class="btn btn-primary" @disabled($autores->isEmpty() || $categorias->isEmpty())>{{ $submitLabel }}</button>
    <a href="{{ $livro ? route('livros.show', $livro) : route('livros.index') }}" class="btn btn-ghost">Cancelar</a>
</div>
