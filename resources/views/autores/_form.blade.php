<div class="form-grid">
    <div class="field">
        <label for="nome">Nome</label>
        <input id="nome" name="nome" type="text" class="form-control" value="{{ old('nome', $autor?->nome) }}" required maxlength="255" autocomplete="name">
        @error('nome')<p class="field-error">{{ $message }}</p>@enderror
    </div>
    <div class="field">
        <label for="nacionalidade">Nacionalidade <span class="muted">(opcional)</span></label>
        <input id="nacionalidade" name="nacionalidade" type="text" class="form-control" value="{{ old('nacionalidade', $autor?->nacionalidade) }}" maxlength="100" autocomplete="country-name">
        @error('nacionalidade')<p class="field-error">{{ $message }}</p>@enderror
    </div>
</div>
<div class="form-actions">
    <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
    <a href="{{ $autor ? route('autores.show', $autor) : route('autores.index') }}" class="btn btn-ghost">Cancelar</a>
</div>
