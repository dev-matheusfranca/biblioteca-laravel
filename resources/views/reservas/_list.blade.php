@php($labels = ['aguardando' => 'Aguardando exemplar', 'disponivel' => 'Disponível para retirada', 'atendida' => 'Retirada realizada', 'cancelada' => 'Cancelada', 'expirada' => 'Prazo expirado', 'inelegivel' => 'Encerrada por inelegibilidade'])
@if($reservas->isEmpty())
    <div class="empty-state"><h2>{{ $staff ? 'Nenhuma reserva encontrada' : 'Você ainda não tem reservas' }}</h2><p>As reservas são feitas no catálogo e atendidas por ordem de solicitação.</p><a class="btn btn-secondary" href="{{ route('catalogo.index') }}">Explorar catálogo</a></div>
@else
@if(!$staff)
<div class="panel-body reservation-list">
@foreach($reservas as $reserva)
    @php($state = $reserva->status->value)
    <article class="reservation-card">
        <h3>{{ $reserva->livro->titulo }}</h3>
        <span class="badge {{ $state === 'disponivel' ? 'badge-success' : 'badge-neutral' }}">{{ $labels[$state] ?? ucfirst($state) }}</span>
        <dl><div><dt>Solicitada em</dt><dd>{{ $reserva->created_at->format('d/m/Y H:i') }}</dd></div>
        @if($state === 'disponivel')<div><dt>Retirar até</dt><dd><strong>{{ $reserva->expira_em?->format('d/m/Y H:i') }}</strong></dd></div>@endif</dl>
        @if(in_array($state, ['aguardando', 'disponivel']))
        <form method="POST" action="{{ route('reservas.destroy', $reserva) }}" data-confirm="Cancelar esta reserva?">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm" type="submit">Cancelar reserva</button></form>
        @endif
    </article>
@endforeach
</div>
@else
<div class="table-wrap"><table class="data-table"><thead><tr><th scope="col">Livro</th>@if($staff)<th scope="col">Leitor</th>@endif<th scope="col">Solicitação</th><th scope="col">Situação</th><th scope="col">Prazo de retirada</th><th scope="col">Ações</th></tr></thead><tbody>
@foreach($reservas as $reserva)
    @php($state = $reserva->status instanceof \BackedEnum ? $reserva->status->value : $reserva->status)
    <tr><td>{{ $reserva->livro->titulo }}@if($staff && $reserva->exemplar)<span class="muted">{{ $reserva->exemplar->codigo_patrimonial }}</span>@endif</td>@if($staff)<td>{{ $reserva->usuario->name }}</td>@endif<td>{{ $reserva->created_at->format('d/m/Y H:i') }}</td><td><span class="badge {{ $state === 'disponivel' ? 'badge-success' : 'badge-neutral' }}">{{ $labels[$state] ?? ucfirst($state) }}</span></td><td>{{ $reserva->expira_em?->format('d/m/Y H:i') ?? ($state === 'aguardando' ? 'Aguardando disponibilidade' : '—') }}</td><td>
    @if(in_array($state, ['aguardando', 'disponivel']))
        @if($staff && $state === 'disponivel')<a class="btn btn-primary btn-sm" href="{{ route('locacoes.create', ['livro_id' => $reserva->livro_id, 'usuario_id' => $reserva->usuario_id]) }}">Registrar retirada</a>@endif
        <form method="POST" action="{{ route($staff ? 'reservas.cancelar' : 'reservas.destroy', $reserva) }}" data-confirm="Cancelar esta reserva?">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm" type="submit">Cancelar reserva</button></form>
    @else<span class="muted">Encerrada</span>@endif
    </td></tr>
@endforeach
</tbody></table></div>
@endif
{{ $reservas->links() }}
@endif
