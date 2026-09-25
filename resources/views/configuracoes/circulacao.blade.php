@extends('layouts.app')
@section('title', 'Regras de circulação')
@section('content')
<header class="page-heading"><div><div class="eyebrow">Administração</div><h1>Regras de circulação</h1><p>As mudanças valem para novas operações. O histórico conserva os parâmetros usados em cada empréstimo e renovação.</p></div></header>
<section class="panel form-panel"><form method="POST" action="{{ route('configuracoes.circulacao.update') }}">@csrf @method('PATCH')
    <input type="hidden" name="expected_version" value="{{ old('expected_version', $policy->version) }}">
    <p class="panel-description">Política atual #{{ $policy->version }} · Horário de São Paulo</p>
    <div class="form-grid">
    @foreach(['loan_days' => ['Prazo do empréstimo (dias corridos)', 1, 365], 'max_open_loans' => ['Limite de empréstimos abertos por leitor', 1, 100], 'max_renewals' => ['Limite de renovações por empréstimo', 0, 20], 'renewal_days' => ['Dias acrescentados em cada renovação', 1, 365], 'pickup_hours' => ['Prazo para retirar uma reserva (horas)', 1, 720]] as $field => [$label, $minimum, $maximum])
        <div class="field"><label for="{{ $field }}">{{ $label }}</label><input class="form-control" id="{{ $field }}" name="{{ $field }}" type="number" min="{{ $minimum }}" max="{{ $maximum }}" required value="{{ old($field, $policy->$field) }}">@error($field)<p class="field-error">{{ $message }}</p>@enderror</div>
    @endforeach
    <input type="hidden" name="timezone" value="America/Sao_Paulo">
    <div class="field field-wide"><input type="hidden" name="blocks_overdue" value="0"><label class="checkbox-field"><input type="checkbox" name="blocks_overdue" value="1" @checked(old('blocks_overdue', $policy->blocks_overdue))> Bloquear novas retiradas e renovações enquanto o leitor tiver atraso</label></div>
    <div class="field field-wide"><label for="reason">Motivo da alteração</label><textarea class="form-control" name="reason" id="reason" rows="3" maxlength="1000" required>{{ old('reason') }}</textarea></div>
    </div>
    <p class="panel-description">Reservas seguem a ordem de solicitação. A renovação acrescenta dias ao vencimento atual e depende da elegibilidade do leitor e da fila de espera.</p>
    <div class="form-actions"><button class="btn btn-primary" type="submit">Salvar nova política</button></div>
</form></section>
<section class="panel"><div class="toolbar"><h2>Histórico de políticas</h2></div><div class="table-wrap"><table class="data-table"><thead><tr><th scope="col">Versão</th><th scope="col">Registrada em</th><th scope="col">Prazo / limite</th><th scope="col">Motivo</th></tr></thead><tbody>
@foreach($history as $previous)<tr><td>#{{ $previous->version }}</td><td>{{ $previous->created_at->format('d/m/Y H:i') }}</td><td>{{ $previous->loan_days }} dias / {{ $previous->max_open_loans }} empréstimos</td><td>{{ $previous->change_reason }}</td></tr>@endforeach
</tbody></table></div></section>
@endsection
