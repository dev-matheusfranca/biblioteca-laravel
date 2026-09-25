<nav class="page-actions" aria-label="Relatórios operacionais">
    <a href="{{ route('relatorios.circulacao') }}" @class(['btn', 'btn-primary' => request()->routeIs('relatorios.circulacao*'), 'btn-secondary' => !request()->routeIs('relatorios.circulacao*')])>Circulação e demanda</a>
    <a href="{{ route('relatorios.fila') }}" @class(['btn', 'btn-primary' => request()->routeIs('relatorios.fila*'), 'btn-secondary' => !request()->routeIs('relatorios.fila*')])>Fila atual</a>
    <a href="{{ route('relatorios.indisponiveis') }}" @class(['btn', 'btn-primary' => request()->routeIs('relatorios.indisponiveis*'), 'btn-secondary' => !request()->routeIs('relatorios.indisponiveis*')])>Exemplares indisponíveis</a>
</nav>
