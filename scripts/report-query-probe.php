<?php

use App\Services\Reports\CirculationReport;
use App\Services\Reports\ReportPeriod;
use App\Services\Reports\ReservationQueueReport;
use App\Services\Reports\UnavailableInventoryReport;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('local') || ! preg_match('/^biblioteca_benchmark_[0-9]+$/', DB::connection()->getDatabaseName())) {
    throw new RuntimeException('Use somente o banco exclusivo de benchmark.');
}
$period = new ReportPeriod(CarbonImmutable::parse('2023-01-01'), CarbonImmutable::parse('2023-12-31'), CarbonImmutable::parse('2023-06-28'));
$report = app(CirculationReport::class);
$queries = ['overdue' => $report->overdueAtReferenceQuery($period), 'demand' => $report->demandQuery($period),
    'queue' => app(ReservationQueueReport::class)->query(), 'unavailable' => app(UnavailableInventoryReport::class)->query()];
$results = [];
foreach ($queries as $name => $query) {
    $start = microtime(true);
    $rows = (clone $query)->limit(25)->get();
    $results[$name] = ['first_page_rows' => $rows->count(), 'duration_ms' => (microtime(true) - $start) * 1000,
        'plan' => DB::select('EXPLAIN FORMAT=JSON '.$query->limit(25)->toSql(), $query->getBindings())];
}
echo json_encode($results, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
