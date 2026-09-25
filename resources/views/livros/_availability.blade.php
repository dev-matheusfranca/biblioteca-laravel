@if($livro->status !== 'ativo')
    <span class="badge badge-neutral">Cadastro inativo</span>
@elseif(($livro->usaExemplares() && $livro->quantidade_disponivel > 0))
    <span class="badge badge-success">Disponível</span>
    <span class="muted">{{ $livro->quantidade_disponivel }} de {{ $livro->quantidade_total }} exemplares</span>
@else
    <span class="badge badge-warning">Indisponível</span>
    <span class="muted">Nenhum exemplar disponível</span>
@endif
