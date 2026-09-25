<?php

use App\Services\Catalog\PublicCatalog;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! app()->environment('local') || ! preg_match('/^biblioteca_benchmark_[0-9]+$/', DB::connection()->getDatabaseName())) {
    throw new RuntimeException('Este diagnóstico exige o banco exclusivo de benchmark.');
}
$catalog = app(PublicCatalog::class);
$metrics = [];
foreach ([false, true, true] as $index => $enabled) {
    config(['catalog.enabled' => $enabled]);
    DB::enableQueryLog();
    DB::flushQueryLog();
    $start = microtime(true);
    $page = $catalog->page('Acervo');
    $queries = DB::getQueryLog();
    $metrics[] = ['mode' => ['uncached', 'cold', 'warm'][$index], 'queries' => count($queries),
        'sql_ms' => array_sum(array_column($queries, 'time')), 'total_ms' => (microtime(true) - $start) * 1000,
        'total_results' => $page['livros']->total()];
}
$plans = [];
foreach (['', 'Acervo'] as $search) {
    $query = $catalog->query($search)->limit(12);
    $plans[] = ['search' => $search, 'explain' => DB::select('EXPLAIN FORMAT=JSON '.$query->toSql(), $query->getBindings())];
}
echo json_encode(['metrics' => $metrics, 'plans' => $plans], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
