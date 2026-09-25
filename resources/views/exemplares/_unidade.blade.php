<fieldset class="inventory-unit"><legend>Unidade física</legend>
    <input type="hidden" name="unidades[{{ $index }}][origem]" value="reconciliacao">
    <div class="field"><label for="codigo-{{ $index }}">Código patrimonial</label><input id="codigo-{{ $index }}" class="form-control" name="unidades[{{ $index }}][codigo_patrimonial]" value="{{ $unidade['codigo_patrimonial'] ?? '' }}" maxlength="64" required autocomplete="off"></div>
    <div class="field"><label for="condicao-{{ $index }}">Condição</label><select id="condicao-{{ $index }}" class="form-control" name="unidades[{{ $index }}][condicao]" data-native-select>@foreach(['circulacao'=>'Em circulação','manutencao'=>'Em manutenção','extraviado'=>'Extraviado','baixado'=>'Baixado'] as $value => $label)<option value="{{ $value }}" @selected(($unidade['condicao'] ?? 'circulacao') === $value)>{{ $label }}</option>@endforeach</select></div>
    <input type="hidden" name="unidades[{{ $index }}][identificacao_fisica]" value="0"><label class="checkbox-field"><input name="unidades[{{ $index }}][identificacao_fisica]" type="checkbox" value="1" @checked($unidade['identificacao_fisica'] ?? false) required> Unidade identificada e código conferido</label>
    <button type="button" class="btn btn-ghost btn-sm" data-remove-unit>Remover esta linha</button>
</fieldset>
