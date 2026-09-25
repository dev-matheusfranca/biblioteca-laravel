<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportRequest;
use App\Models\Exemplar;
use App\Models\Reserva;
use App\Services\Reports\CirculationReport;
use App\Services\Reports\CsvReport;
use App\Services\Reports\ReportPeriod;
use App\Services\Reports\ReservationQueueReport;
use App\Services\Reports\UnavailableInventoryReport;
use Carbon\CarbonImmutable;
use Generator;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function circulation(ReportRequest $request, CirculationReport $report): View
    {
        $period = $request->period();
        $search = $request->search();
        $population = $request->population();
        $summary = $report->summary($period, $search);
        $demand = $report->demandQuery($period, $search)
            ->paginate($request->perPage(), ['*'], 'demand_page')
            ->withQueryString();
        $loans = $report->loansQuery($period, $population, $search)
            ->paginate($request->perPage(), ['*'], 'loans_page')
            ->withQueryString();
        $generatedAt = CarbonImmutable::now((string) config('app.timezone'));

        return view('relatorios.circulacao', compact(
            'demand',
            'generatedAt',
            'loans',
            'period',
            'population',
            'report',
            'summary',
        ));
    }

    public function circulationCsv(ReportRequest $request, CirculationReport $report, CsvReport $csv): StreamedResponse
    {
        $period = $request->period();
        $search = $request->search();
        $population = $request->population();
        $summary = $report->summary($period, $search);
        $generatedAt = CarbonImmutable::now((string) config('app.timezone'));

        return $csv->download(
            "circulacao-{$period->startDate()}-{$period->endDate()}.csv",
            ['Seção', 'Chave ou ID', 'Título', 'Data do empréstimo', 'Prazo aplicável', 'Encerrado em', 'Empréstimos no período', 'Reservas no período', 'Valor', 'Observação'],
            function () use ($generatedAt, $period, $population, $report, $search, $summary): Generator {
                yield ['metadado', 'gerado_em', null, null, null, null, null, null, $generatedAt->toIso8601String(), null];
                yield ['metadado', 'fuso_horario', null, null, null, null, null, null, (string) config('app.timezone'), null];
                yield ['metadado', 'periodo', null, null, null, null, null, null, $period->startDate().' a '.$period->endDate(), null];
                yield ['metadado', 'data_referencia', null, null, null, null, null, null, $period->referenceDate(), 'Fim do dia no fuso informado'];
                yield ['metadado', 'populacao_emprestimos', null, null, null, null, null, null, CirculationReport::POPULATION_LABELS[$population], null];
                yield ['metadado', 'filtro_titulo', null, null, null, null, null, null, $search, null];

                $labels = [
                    'checkouts' => 'Empréstimos iniciados no período',
                    'returns' => 'Devoluções físicas no período',
                    'losses' => 'Encerramentos por perda no período',
                    'reservation_requests' => 'Solicitações de reserva no período',
                    'open_at_reference' => 'Empréstimos abertos na referência',
                    'overdue_at_reference' => 'Empréstimos atrasados na referência',
                ];
                foreach ($labels as $key => $label) {
                    yield ['indicador', $key, null, null, null, null, null, null, $summary[$key], $label];
                }

                foreach ($report->demandQuery($period, $search)->cursor() as $book) {
                    yield [
                        'demanda',
                        $book->id,
                        (string) $book->getAttribute('titulo'),
                        null,
                        null,
                        null,
                        (int) $book->getAttribute('emprestimos_periodo'),
                        (int) $book->getAttribute('reservas_periodo'),
                        null,
                        'Eventos distintos; leitores não são deduplicados',
                    ];
                }

                foreach ($report->loansQuery($period, $population, $search)->cursor() as $loan) {
                    yield [
                        'emprestimo',
                        $loan->id,
                        (string) $loan->getAttribute('titulo'),
                        $loan->data_locacao,
                        (string) $loan->getAttribute('prazo_exibido'),
                        $loan->encerrado_em === null
                            ? null
                            : CarbonImmutable::parse((string) $loan->encerrado_em)->toIso8601String(),
                        null,
                        null,
                        null,
                        CirculationReport::POPULATION_LABELS[$population].($population === 'period'
                            ? '; prazo atual armazenado'
                            : '; prazo histórico na referência'),
                    ];
                }
            },
            $this->csvMetadataHeaders($generatedAt, $period),
        );
    }

    public function queue(ReportRequest $request, ReservationQueueReport $report): View
    {
        $generatedAt = CarbonImmutable::now((string) config('app.timezone'));
        $reservations = $report->query($request->search())
            ->paginate($request->perPage())
            ->withQueryString();

        return view('relatorios.fila', compact('generatedAt', 'report', 'reservations'));
    }

    public function queueCsv(ReportRequest $request, ReservationQueueReport $report, CsvReport $csv): StreamedResponse
    {
        $generatedAt = CarbonImmutable::now((string) config('app.timezone'));

        return $csv->download(
            'fila-reservas-'.$generatedAt->format('Y-m-d-His').'.csv',
            ['Título', 'Posição atual', 'Solicitada em', 'Espera atual em horas'],
            fn () => $report->query($request->search())->cursor()->map(
                fn (Reserva $reservation): array => [
                    (string) $reservation->getAttribute('titulo'),
                    (int) $reservation->getAttribute('posicao'),
                    $reservation->created_at->toIso8601String(),
                    $report->waitingHours($reservation, $generatedAt),
                ]
            ),
            $this->csvMetadataHeaders($generatedAt),
        );
    }

    public function unavailable(ReportRequest $request, UnavailableInventoryReport $report): View
    {
        $generatedAt = CarbonImmutable::now((string) config('app.timezone'));
        $copies = $report->query($request->search())
            ->paginate($request->perPage())
            ->withQueryString();

        return view('relatorios.indisponiveis', compact('copies', 'generatedAt', 'report'));
    }

    public function unavailableCsv(ReportRequest $request, UnavailableInventoryReport $report, CsvReport $csv): StreamedResponse
    {
        $generatedAt = CarbonImmutable::now((string) config('app.timezone'));

        return $csv->download(
            'exemplares-indisponiveis-'.$generatedAt->format('Y-m-d-His').'.csv',
            ['Título', 'Código patrimonial', 'Motivo atual'],
            fn () => $report->query($request->search())->cursor()->map(
                fn (Exemplar $copy): array => [
                    (string) $copy->getAttribute('titulo'),
                    $copy->codigo_patrimonial,
                    $report->reasonLabel($copy),
                ]
            ),
            $this->csvMetadataHeaders($generatedAt),
        );
    }

    /** @return array<string, string> */
    private function csvMetadataHeaders(CarbonImmutable $generatedAt, ?ReportPeriod $period = null): array
    {
        $headers = [
            'X-Report-Generated-At' => $generatedAt->toIso8601String(),
            'X-Report-Timezone' => (string) config('app.timezone'),
        ];
        if ($period !== null) {
            $headers['X-Report-Period-Start'] = $period->startDate();
            $headers['X-Report-Period-End'] = $period->endDate();
            $headers['X-Report-Reference-Date'] = $period->referenceDate();
        }

        return $headers;
    }
}
