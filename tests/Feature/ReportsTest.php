<?php

namespace Tests\Feature;

use App\Enums\ExemplarCondition;
use App\Models\CirculationPolicy;
use App\Models\Exemplar;
use App\Models\Livro;
use App\Models\LoanRenewal;
use App\Models\Locacao;
use App\Models\Reserva;
use App\Models\User;
use App\Services\Reports\CirculationReport;
use App\Services\Reports\CsvReport;
use App\Services\Reports\ReportPeriod;
use App\Services\Reports\ReservationQueueReport;
use App\Services\Reports\UnavailableInventoryReport;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PhysicalCatalog;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-25 12:00:00', 'America/Sao_Paulo'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_circulation_reconciles_period_flows_and_historical_due_date(): void
    {
        $reader = User::factory()->reader()->create();
        $policy = CirculationPolicy::query()->firstOrFail();
        [, , $demandedBook, $copies] = PhysicalCatalog::book(['titulo' => 'Título demandado'], 4);
        [, , $lostBook, $lostCopies] = PhysicalCatalog::book(['titulo' => 'Título perdido']);

        $renewedAfterReference = $this->loan($reader, $demandedBook, $copies[0], [
            'data_locacao' => '2026-09-01',
            'data_devolucao' => '2026-09-30',
        ]);
        $renewal = LoanRenewal::create([
            'locacao_id' => $renewedAfterReference->id,
            'actor_id' => $reader->id,
            'policy_id' => $policy->id,
            'previous_due_date' => '2026-09-10',
            'new_due_date' => '2026-09-30',
            'policy_snapshot' => $policy->snapshot(),
        ]);
        $this->at($renewal, '2026-09-20 09:00:00');

        $this->loan($reader, $demandedBook, $copies[1], [
            'data_locacao' => '2026-09-05',
            'data_devolucao' => '2026-09-20',
            'data_devolvido' => '2026-09-16',
            'encerrado_em' => '2026-09-16 10:00:00',
            'encerramento_motivo' => 'devolucao',
            'status' => 'devolvida',
            'active_exemplar_id' => null,
        ]);
        $this->loan($reader, $demandedBook, $copies[2], [
            'data_locacao' => '2026-09-02',
            'data_devolucao' => '2026-09-12',
            'data_devolvido' => '2026-09-14',
            'encerrado_em' => '2026-09-14 10:00:00',
            'encerramento_motivo' => 'devolucao',
            'status' => 'devolvida',
            'active_exemplar_id' => null,
        ]);
        $this->loan($reader, $lostBook, $lostCopies[0], [
            'data_locacao' => '2026-09-03',
            'data_devolucao' => '2026-09-17',
            'encerrado_em' => '2026-09-12 11:00:00',
            'encerramento_motivo' => 'perda',
            'status' => 'devolvida',
            'active_exemplar_id' => null,
        ]);

        foreach (['2026-09-08 08:00:00', '2026-09-09 08:00:00'] as $createdAt) {
            $reservation = Reserva::create([
                'usuario_id' => $reader->id,
                'livro_id' => $demandedBook->id,
                'status' => 'cancelada',
                'active_key' => null,
                'policy_id' => $policy->id,
                'policy_snapshot' => $policy->snapshot(),
                'encerrada_em' => $createdAt,
                'encerramento_motivo' => 'teste de relatório',
            ]);
            $this->at($reservation, $createdAt);
        }

        $period = new ReportPeriod(
            CarbonImmutable::parse('2026-09-01', 'America/Sao_Paulo'),
            CarbonImmutable::parse('2026-09-30', 'America/Sao_Paulo'),
            CarbonImmutable::parse('2026-09-15', 'America/Sao_Paulo'),
        );
        $report = app(CirculationReport::class);

        $this->assertSame([
            'checkouts' => 4,
            'returns' => 2,
            'losses' => 1,
            'reservation_requests' => 2,
            'open_at_reference' => 2,
            'overdue_at_reference' => 1,
        ], $report->summary($period));
        $demand = $report->demandQuery($period)->get();
        $this->assertSame('Título demandado', $demand->first()->titulo);
        $this->assertSame(3, (int) $demand->first()->emprestimos_periodo);
        $this->assertSame(2, (int) $demand->first()->reservas_periodo);
        $this->assertSame(1, (int) $demand->last()->emprestimos_periodo);
        $this->assertSame(0, (int) $demand->last()->reservas_periodo);
    }

    public function test_current_queue_uses_fifo_position_and_current_age_without_historical_average(): void
    {
        $policy = CirculationPolicy::query()->firstOrFail();
        [, , $alpha] = PhysicalCatalog::book(['titulo' => 'Alpha'], 0);
        [, , $beta] = PhysicalCatalog::book(['titulo' => 'Beta'], 0);
        $readers = User::factory()->reader()->count(4)->create();

        $first = $this->reservation($readers[0], $alpha, $policy, 'aguardando', '2026-09-23 10:00:00');
        $second = $this->reservation($readers[1], $alpha, $policy, 'aguardando', '2026-09-23 10:00:00');
        $third = $this->reservation($readers[2], $beta, $policy, 'aguardando', '2026-09-24 09:00:00');
        $this->reservation($readers[3], $alpha, $policy, 'disponivel', '2026-09-22 09:00:00');

        $report = app(ReservationQueueReport::class);
        $rows = $report->query()->get();

        $this->assertSame([$first->id, $second->id, $third->id], $rows->pluck('id')->all());
        $this->assertSame([1, 2, 1], $rows->pluck('posicao')->map(fn ($value) => (int) $value)->all());
        $this->assertSame(50, $report->waitingHours($rows->first(), CarbonImmutable::now('America/Sao_Paulo')));
    }

    public function test_unavailable_inventory_assigns_one_current_reason_by_precedence(): void
    {
        $reader = User::factory()->reader()->create();
        $policy = CirculationPolicy::query()->firstOrFail();
        [, , $book, $copies] = PhysicalCatalog::book(['titulo' => 'Inventário'], 6);
        [, , $inactiveBook, $inactiveCopies] = PhysicalCatalog::book(['titulo' => 'Título inativo'], 1);
        [, , $reconciliationBook, $reconciliationCopies] = PhysicalCatalog::book(['titulo' => 'Em reconciliação'], 1);
        $inactiveBook->update(['status' => 'inativo']);
        $reconciliationBook->update(['modo_acervo' => 'reconciliacao']);

        $copies[0]->update(['identificacao_fisica' => false, 'condicao' => ExemplarCondition::Maintenance]);
        $copies[1]->update(['condicao' => ExemplarCondition::Maintenance]);
        $copies[2]->update(['condicao' => ExemplarCondition::Missing]);
        $this->loan($reader, $book, $copies[3], []);
        Reserva::create([
            'usuario_id' => $reader->id,
            'livro_id' => $book->id,
            'status' => 'disponivel',
            'active_key' => Reserva::activeKey($reader->id, $book->id),
            'exemplar_id' => $copies[4]->id,
            'active_exemplar_id' => $copies[4]->id,
            'policy_id' => $policy->id,
            'policy_snapshot' => $policy->snapshot(),
            'disponivel_em' => now(),
            'expira_em' => now()->addHours(48),
        ]);

        $report = app(UnavailableInventoryReport::class);
        $reasons = $report->query()->get()->keyBy('codigo_patrimonial')
            ->map(fn (Exemplar $copy) => (string) $copy->getAttribute('motivo_indisponibilidade'));

        $this->assertCount(7, $reasons);
        $this->assertSame('nao_identificado', $reasons[$copies[0]->codigo_patrimonial]);
        $this->assertSame('manutencao', $reasons[$copies[1]->codigo_patrimonial]);
        $this->assertSame('extraviado', $reasons[$copies[2]->codigo_patrimonial]);
        $this->assertSame('emprestado', $reasons[$copies[3]->codigo_patrimonial]);
        $this->assertSame('reservado', $reasons[$copies[4]->codigo_patrimonial]);
        $this->assertArrayNotHasKey($copies[5]->codigo_patrimonial, $reasons);
        $this->assertSame('titulo_inativo', $reasons[$inactiveCopies[0]->codigo_patrimonial]);
        $this->assertSame('reconciliacao', $reasons[$reconciliationCopies[0]->codigo_patrimonial]);
    }

    public function test_report_routes_are_restricted_and_csv_matches_the_screen_without_pii_or_formulas(): void
    {
        $staff = User::factory()->librarian()->create();
        $reader = User::factory()->reader()->create([
            'name' => 'Pessoa Confidencial',
            'email' => 'confidencial@example.test',
        ]);
        [, , $book, $copies] = PhysicalCatalog::book(['titulo' => '  =FÓRMULA("a\\b")'], 1);
        $this->loan($reader, $book, $copies[0], [
            'data_locacao' => '2026-09-10',
            'data_devolucao' => '2026-09-24',
        ]);
        $filters = [
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-25',
            'reference_date' => '2026-09-25',
        ];

        $this->get(route('relatorios.circulacao', $filters))->assertRedirect(route('login'));
        $this->actingAs($reader)->get(route('relatorios.circulacao', $filters))->assertForbidden();

        $screen = $this->actingAs($staff)->get(route('relatorios.circulacao', $filters))
            ->assertOk()
            ->assertSee('=FÓRMULA')
            ->assertSee('1');
        $this->assertStringNotContainsString($reader->email, $screen->getContent());
        $this->assertStringNotContainsString($reader->name, $screen->getContent());

        $csvResponse = $this->actingAs($staff)->get(route('relatorios.circulacao.csv', $filters))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertHeader('X-Report-Timezone', 'America/Sao_Paulo')
            ->assertHeader('X-Report-Reference-Date', '2026-09-25');
        $csv = $csvResponse->streamedContent();
        $this->assertStringContainsString("'  =FÓRMULA", $csv);
        $this->assertStringContainsString('indicador;checkouts', $csv);
        $this->assertStringContainsString('demanda;', $csv);
        $this->assertStringContainsString('emprestimo;', $csv);
        $this->assertStringContainsString('Empréstimos iniciados no período', $csv);
        $this->assertStringNotContainsString($reader->email, $csv);
        $this->assertStringNotContainsString($reader->name, $csv);

        $demandRow = collect(preg_split('/\R/', $csv) ?: [])
            ->map(fn (string $line): array => str_getcsv($line, ';', '"', ''))
            ->first(fn (array $row): bool => ($row[0] ?? null) === 'demanda');
        $this->assertIsArray($demandRow);
        $this->assertSame("'  =FÓRMULA(\"a\\b\")", $demandRow[2]);
        $this->assertSame('1', $demandRow[6]);
        $this->assertSame('0', $demandRow[7]);
    }

    public function test_title_filter_applies_to_indicators_demand_loans_queue_and_inventory(): void
    {
        $staff = User::factory()->librarian()->create();
        $reader = User::factory()->reader()->create();
        $policy = CirculationPolicy::query()->firstOrFail();
        [, , $alpha, $alphaCopies] = PhysicalCatalog::book(['titulo' => 'Alpha filtrado'], 2);
        [, , $beta, $betaCopies] = PhysicalCatalog::book(['titulo' => 'Beta oculto'], 2);
        $this->loan($reader, $alpha, $alphaCopies[0], []);
        $this->loan($reader, $beta, $betaCopies[0], []);
        $this->reservation($reader, $alpha, $policy, 'aguardando', '2026-09-24 08:00:00');
        $this->reservation(User::factory()->reader()->create(), $beta, $policy, 'aguardando', '2026-09-24 09:00:00');
        $alphaCopies[1]->update(['condicao' => ExemplarCondition::Maintenance]);
        $betaCopies[1]->update(['condicao' => ExemplarCondition::Maintenance]);

        $filters = [
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-25',
            'reference_date' => '2026-09-25',
            'q' => 'Alpha',
        ];
        $response = $this->actingAs($staff)->get(route('relatorios.circulacao', $filters))
            ->assertOk()
            ->assertSee('Alpha filtrado')
            ->assertDontSee('Beta oculto')
            ->assertViewHas('summary', fn (array $summary): bool => $summary['checkouts'] === 1);
        $this->assertStringNotContainsString('Beta oculto', $response->getContent());

        $this->actingAs($staff)->get(route('relatorios.fila', ['q' => 'Alpha']))
            ->assertOk()->assertSee('Alpha filtrado')->assertDontSee('Beta oculto');
        $this->actingAs($staff)->get(route('relatorios.indisponiveis', ['q' => 'Alpha']))
            ->assertOk()->assertSee('Alpha filtrado')->assertDontSee('Beta oculto');

        $queueCsv = $this->actingAs($staff)->get(route('relatorios.fila.csv', ['q' => 'Alpha']))
            ->assertOk()->assertHeader('X-Report-Timezone', 'America/Sao_Paulo')->streamedContent();
        $this->assertStringContainsString('Alpha filtrado', $queueCsv);
        $this->assertStringNotContainsString('Beta oculto', $queueCsv);
        $inventoryCsv = $this->actingAs($staff)->get(route('relatorios.indisponiveis.csv', ['q' => 'Alpha']))
            ->assertOk()->assertHeader('X-Report-Timezone', 'America/Sao_Paulo')->streamedContent();
        $this->assertStringContainsString('Alpha filtrado', $inventoryCsv);
        $this->assertStringNotContainsString('Beta oculto', $inventoryCsv);
    }

    public function test_csv_neutralizes_control_prefixes_and_round_trips_quotes_and_backslashes(): void
    {
        $response = app(CsvReport::class)->download(
            'seguro.csv',
            ['Campo'],
            fn (): array => [["\n=1+1"], ["\ttexto"], ['  @cmd"a\\b']],
        );

        $stream = fopen('php://temp', 'w+b');
        $this->assertIsResource($stream);
        ob_start();
        $response->sendContent();
        $contents = ob_get_clean();
        fwrite($stream, preg_replace('/^\xEF\xBB\xBF/', '', is_string($contents) ? $contents : '') ?? '');
        rewind($stream);
        $rows = [];
        while (($row = fgetcsv($stream, null, ';', '"', '')) !== false) {
            $rows[] = $row;
        }
        fclose($stream);

        $this->assertSame(['Campo'], $rows[0]);
        $this->assertSame("'\n=1+1", $rows[1][0]);
        $this->assertSame("'\ttexto", $rows[2][0]);
        $this->assertSame("'  @cmd\"a\\b", $rows[3][0]);
    }

    public function test_report_filters_reject_inverted_future_and_excessive_periods(): void
    {
        $staff = User::factory()->librarian()->create();

        $this->actingAs($staff)->get(route('relatorios.circulacao', [
            'period_start' => '2026-09-20',
            'period_end' => '2026-09-10',
            'reference_date' => '2026-09-26',
        ]))->assertSessionHasErrors(['period_start', 'reference_date']);
        $this->actingAs($staff)->get(route('relatorios.circulacao', [
            'period_start' => '2025-01-01',
            'period_end' => '2026-09-25',
        ]))->assertSessionHasErrors('period_start');

        $this->actingAs($staff)->get(route('relatorios.circulacao', [
            'period_start' => '2026-09-01',
        ]))->assertOk()->assertViewHas('period', fn (ReportPeriod $period): bool => $period->endDate() === '2026-09-25');
        $this->actingAs($staff)->get(route('relatorios.circulacao', [
            'period_end' => 'data-invalida',
        ]))->assertSessionHasErrors('period_end');
    }

    public function test_large_related_lists_are_paginated_on_detail_pages(): void
    {
        $staff = User::factory()->librarian()->create();
        $reader = User::factory()->reader()->create();
        [$author, $category, $book] = PhysicalCatalog::book(['titulo' => 'Livro principal']);
        foreach (range(1, 11) as $number) {
            PhysicalCatalog::book(['titulo' => "Relacionado {$number}"], 0, $author, $category);
            Locacao::create([
                'usuario_id' => $reader->id,
                'livro_id' => $book->id,
                'data_locacao' => '2026-08-01',
                'data_devolucao' => '2026-08-15',
                'data_devolvido' => '2026-08-10',
                'status' => 'devolvida',
                'encerrado_em' => '2026-08-10 10:00:00',
                'encerramento_motivo' => 'devolucao',
            ]);
        }

        $this->actingAs($staff)->get(route('autores.show', $author))->assertOk()
            ->assertViewHas('livros', fn ($books) => $books->count() === 10 && $books->total() === 12);
        $this->actingAs($staff)->get(route('categorias.show', $category))->assertOk()
            ->assertViewHas('livros', fn ($books) => $books->count() === 10 && $books->total() === 12);
        $this->actingAs($staff)->get(route('livros.show', $book))->assertOk()
            ->assertViewHas('locacoes', fn ($loans) => $loans->count() === 10 && $loans->total() === 11);
    }

    /** @param array<string, mixed> $attributes */
    private function loan(User $reader, Livro $book, Exemplar $copy, array $attributes): Locacao
    {
        return Locacao::create(array_merge([
            'usuario_id' => $reader->id,
            'livro_id' => $book->id,
            'exemplar_id' => $copy->id,
            'active_exemplar_id' => $copy->id,
            'data_locacao' => '2026-09-01',
            'data_devolucao' => '2026-09-15',
            'status' => 'ativa',
        ], $attributes));
    }

    private function reservation(User $reader, Livro $book, CirculationPolicy $policy, string $status, string $createdAt): Reserva
    {
        $reservation = Reserva::create([
            'usuario_id' => $reader->id,
            'livro_id' => $book->id,
            'status' => $status,
            'active_key' => Reserva::activeKey($reader->id, $book->id),
            'policy_id' => $policy->id,
            'policy_snapshot' => $policy->snapshot(),
        ]);
        $this->at($reservation, $createdAt);

        return $reservation;
    }

    private function at(object $model, string $timestamp): void
    {
        $model->forceFill(['created_at' => $timestamp, 'updated_at' => $timestamp])->save();
    }
}
