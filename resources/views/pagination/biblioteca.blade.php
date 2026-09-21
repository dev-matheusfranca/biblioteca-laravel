@if ($paginator->hasPages())
<nav class="pagination-bar" aria-label="Paginação">
    <span>Exibindo {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} de {{ $paginator->total() }} registros</span>
    <div class="pagination-links">
        @if ($paginator->onFirstPage())
            <span class="page-link" aria-disabled="true">Anterior</span>
        @else
            <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">Anterior</a>
        @endif
        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="page-dots" aria-hidden="true">{{ $element }}</span>
            @else
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="page-link" aria-current="page" aria-label="Página {{ $page }}">{{ $page }}</span>
                    @else
                        <a class="page-link" href="{{ $url }}" aria-label="Ir para a página {{ $page }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach
        @if ($paginator->hasMorePages())
            <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next">Próxima</a>
        @else
            <span class="page-link" aria-disabled="true">Próxima</span>
        @endif
    </div>
</nav>
@endif
