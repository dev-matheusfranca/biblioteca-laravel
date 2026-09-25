<section class="panel"><div class="panel-body"><h2>Renovação</h2>
@if($podeRenovar)
    <p>O prazo será ampliado de acordo com a política vigente. A disponibilidade será confirmada ao enviar.</p>
    <form method="POST" action="{{ route($staff ? 'locacoes.renovar' : 'portal.emprestimos.renovar', $locacao) }}">@csrf<button class="btn btn-primary" type="submit">Renovar empréstimo</button></form>
@else
    <p>{{ $motivoRenovacao }}</p>
@endif
</div>
@if($locacao->renovacoes->isNotEmpty())
<div class="table-wrap"><table class="data-table"><thead><tr><th scope="col">Renovação</th><th scope="col">Prazo anterior</th><th scope="col">Novo prazo</th></tr></thead><tbody>
@foreach($locacao->renovacoes as $renovacao)<tr><td>{{ $renovacao->created_at->format('d/m/Y H:i') }}</td><td>{{ \Illuminate\Support\Carbon::parse($renovacao->previous_due_date)->format('d/m/Y') }}</td><td>{{ \Illuminate\Support\Carbon::parse($renovacao->new_due_date)->format('d/m/Y') }}</td></tr>@endforeach
</tbody></table></div>
@endif
</section>
