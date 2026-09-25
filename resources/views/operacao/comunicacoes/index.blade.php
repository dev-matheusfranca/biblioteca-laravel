@extends('layouts.app')
@section('title', 'Comunicações')
@section('content')
@php($statusLabels = ['pending'=>'Pendente', 'processing'=>'Em processamento', 'sent'=>'Enviada', 'cancelled'=>'Cancelada', 'failed'=>'Falha'])
@php($typeLabels = ['reservation.available'=>'Reserva disponível', 'loan.due_soon'=>'Prazo próximo', 'loan.overdue'=>'Empréstimo em atraso'])
<header class="page-heading"><div><div class="eyebrow">Operação</div><h1>Comunicações</h1><p>Acompanhe os avisos persistidos e reenvie falhas após corrigir a causa.</p></div><a class="btn btn-secondary" href="{{ url('/horizon') }}">Ver filas no Horizon</a></header>
<section class="panel"><div class="toolbar"><h2>Histórico de processamento</h2><p class="muted">{{ $eventos->total() }} evento(s)</p></div>
@if($eventos->isEmpty())<div class="empty-state"><h2>Nenhuma comunicação preparada</h2><p>As reservas disponíveis e os lembretes de prazo gerarão os próximos eventos.</p></div>
@else
<div class="table-wrap"><table class="data-table"><thead><tr><th scope="col">Evento</th><th scope="col">Tipo</th><th scope="col">Estado</th><th scope="col">Tentativas</th><th scope="col">Registrado em</th><th scope="col">Diagnóstico</th><th scope="col">Ações</th></tr></thead><tbody>
@foreach($eventos as $evento)
<tr><td>#{{ $evento->id }}</td><td>{{ $typeLabels[$evento->type->value] ?? $evento->type->value }}</td><td><span class="badge {{ $evento->status->value === 'failed' ? 'badge-warning' : 'badge-neutral' }}">{{ $statusLabels[$evento->status->value] ?? $evento->status->value }}</span></td><td>{{ $evento->attempts }}</td><td>{{ $evento->created_at->format('d/m/Y H:i') }}</td><td>{{ $evento->error_code ?: 'Sem falha registrada' }}<span class="muted">{{ $evento->correlation_id }}</span></td><td>@if($evento->status->value === 'failed')<form method="POST" action="{{ route('operacao.comunicacoes.reprocessar', $evento) }}">@csrf<button class="btn btn-secondary btn-sm" type="submit">Reprocessar aviso</button></form>@else<span class="muted">—</span>@endif</td></tr>
@endforeach
</tbody></table></div>{{ $eventos->links() }}
@endif
</section>
@endsection
