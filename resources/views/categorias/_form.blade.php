<div class="form-grid">
    <div class="field field-wide"><label for="nome">Nome</label><input id="nome" name="nome" type="text" class="form-control" value="{{ old('nome', $categoria?->nome) }}" required maxlength="255" autocomplete="off">@error('nome')<p class="field-error">{{ $message }}</p>@enderror</div>
    <div class="field field-wide"><label for="descricao">Descrição <span class="muted">(opcional)</span></label><textarea id="descricao" name="descricao" class="form-control" rows="4" maxlength="1000">{{ old('descricao', $categoria?->descricao) }}</textarea>@error('descricao')<p class="field-error">{{ $message }}</p>@enderror</div>
</div>
<div class="form-actions"><button type="submit" class="btn btn-primary">{{ $submitLabel }}</button><a href="{{ $categoria ? route('categorias.show', $categoria) : route('categorias.index') }}" class="btn btn-ghost">Cancelar</a></div>
