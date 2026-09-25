@forelse($avisos as $aviso)
<article class="reservation-card">
    <h3>{{ $aviso->title }}</h3>
    <p>{{ $aviso->body }}</p>
    <p class="muted">{{ $aviso->created_at->format('d/m/Y H:i') }} · {{ $aviso->read_at ? 'Lido' : 'Não lido' }}</p>
    @if($aviso->cancelled_at)<p class="muted">Aviso encerrado. Consulte a situação atual do empréstimo ou da reserva.</p>
    @elseif(!$aviso->read_at)<form method="POST" action="{{ route('avisos.read', $aviso) }}">@csrf @method('PATCH')<button class="btn btn-secondary btn-sm" type="submit">Marcar como lido</button></form>@endif
</article>
@empty
<div class="empty-state"><h3>Nenhum aviso por aqui</h3><p>Os lembretes de prazo e a disponibilidade de reservas aparecerão neste espaço.</p></div>
@endforelse
