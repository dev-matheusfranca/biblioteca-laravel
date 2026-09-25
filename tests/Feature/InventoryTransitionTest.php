<?php

namespace Tests\Feature;

use App\Actions\Circulation\CheckoutExemplar;
use App\Actions\Circulation\CloseLoan;
use App\Enums\AcervoMode;
use App\Models\Autor;
use App\Models\Categoria;
use App\Models\Exemplar;
use App\Models\Livro;
use App\Models\Locacao;
use App\Models\User;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryTransitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_title_rejects_new_loan_until_reconciled(): void
    {
        [$staff, $reader, $book] = $this->context();
        $this->expectException(DomainException::class);
        app(CheckoutExemplar::class)->execute($staff, $reader->id, $book->id, now()->addWeek()->toDateString());
    }

    public function test_reconciliation_form_renders_for_staff(): void
    {
        [, , $book] = $this->context();
        $this->actingAs(User::factory()->librarian()->create())->get(route('livros.reconciliacao.edit', $book))->assertOk()->assertViewHas('locacoesAbertas');
    }

    public function test_reconciliation_binds_open_loan_to_real_copy(): void
    {
        [$staff, $reader, $book] = $this->context();
        $loan = Locacao::create(['usuario_id' => $reader->id, 'livro_id' => $book->id, 'data_locacao' => today(), 'data_devolucao' => today()->addWeek(), 'status' => 'ativa']);
        $this->actingAs($staff)->post(route('livros.reconciliacao.store', $book), $this->reconciliationPayload($loan, true))->assertRedirect(route('exemplares.index', $book));
        $this->assertSame(AcervoMode::Copies, $book->fresh()->modo_acervo);
        $this->assertNotNull($loan->fresh()->exemplar_id);
    }

    public function test_reconciliation_rejects_missing_physical_confirmation(): void
    {
        [$staff,, $book] = $this->context();
        $payload = $this->reconciliationPayload(null, true);
        $payload['unidades'][0]['identificacao_fisica'] = false;
        $this->actingAs($staff)->post(route('livros.reconciliacao.store', $book), $payload)->assertSessionHasErrors('unidades.0.identificacao_fisica');
        $this->assertSame(AcervoMode::Reconciliation, $book->fresh()->modo_acervo);
    }

    public function test_divergence_requires_confirmation_and_reason(): void
    {
        [$staff,, $book] = $this->context(['quantidade_total' => 2, 'quantidade_disponivel' => 2]);
        $this->actingAs($staff)->post(route('livros.reconciliacao.store', $book), $this->reconciliationPayload(null, false))->assertSessionHasErrors('confirmar_divergencia');
    }

    public function test_reconciliation_requires_divergence_confirmation_when_a_unit_is_in_maintenance(): void
    {
        [$staff,, $book] = $this->context();
        $payload = $this->reconciliationPayload(null, false);
        $payload['unidades'][0]['condicao'] = 'manutencao';

        $this->actingAs($staff)
            ->post(route('livros.reconciliacao.store', $book), $payload)
            ->assertSessionHasErrors('confirmar_divergencia');

        $this->assertSame(AcervoMode::Reconciliation, $book->fresh()->modo_acervo);
        $this->assertDatabaseCount('exemplares', 0);
    }

    public function test_reconciliation_rejects_an_open_loan_mapped_to_a_maintenance_copy(): void
    {
        [$staff, $reader, $book] = $this->context(['quantidade_disponivel' => 0]);
        $loan = Locacao::create([
            'usuario_id' => $reader->id,
            'livro_id' => $book->id,
            'data_locacao' => today(),
            'data_devolucao' => today()->addWeek(),
            'status' => 'ativa',
        ]);
        $payload = $this->reconciliationPayload($loan, true);
        $payload['unidades'][0]['condicao'] = 'manutencao';

        $this->actingAs($staff)
            ->post(route('livros.reconciliacao.store', $book), $payload)
            ->assertSessionHasErrors('vinculacoes');

        $this->assertSame(AcervoMode::Reconciliation, $book->fresh()->modo_acervo);
        $this->assertDatabaseCount('exemplares', 0);
        $this->assertNull($loan->fresh()->exemplar_id);
    }

    public function test_reconciliation_validates_a_patron_code_already_used_by_another_title(): void
    {
        [$staff,, $book] = $this->context();
        $author = Autor::create(['nome' => 'Outra autora']);
        $category = Categoria::create(['nome' => 'Outra categoria']);
        $otherBook = Livro::create([
            'titulo' => 'Outro título',
            'autor_id' => $author->id,
            'categoria_id' => $category->id,
            'quantidade_total' => 1,
            'quantidade_disponivel' => 0,
            'status' => 'ativo',
            'modo_acervo' => AcervoMode::Copies,
        ]);
        Exemplar::create([
            'livro_id' => $otherBook->id,
            'codigo_patrimonial' => 'P-001',
            'condicao' => 'circulacao',
            'origem' => 'teste',
            'identificacao_fisica' => true,
        ]);

        $this->actingAs($staff)
            ->post(route('livros.reconciliacao.store', $book), $this->reconciliationPayload(null, true))
            ->assertSessionHasErrors('unidades.0.codigo_patrimonial');

        $this->assertDatabaseCount('exemplares', 1);
        $this->assertSame(AcervoMode::Reconciliation, $book->fresh()->modo_acervo);
    }

    public function test_duplicate_copy_binding_rolls_back_reconciliation(): void
    {
        [$staff,$reader,$book] = $this->context(['quantidade_total' => 2, 'quantidade_disponivel' => 0]);
        $one = Locacao::create(['usuario_id' => $reader->id, 'livro_id' => $book->id, 'data_locacao' => today(), 'data_devolucao' => today()->addWeek(), 'status' => 'ativa']);
        $two = Locacao::create(['usuario_id' => User::factory()->reader()->create()->id, 'livro_id' => $book->id, 'data_locacao' => today(), 'data_devolucao' => today()->addWeek(), 'status' => 'ativa']);
        $p = $this->reconciliationPayload(null, true);
        $p['unidades'][] = ['codigo_patrimonial' => 'P-002', 'condicao' => 'circulacao', 'identificacao_fisica' => true];
        $p['vinculacoes'] = [$one->id => 'P-001', $two->id => 'P-001'];
        $this->actingAs($staff)->post(route('livros.reconciliacao.store', $book), $p)->assertSessionHasErrors();
        $this->assertDatabaseCount('exemplares', 0);
    }

    public function test_maintenance_copy_cannot_be_loaned_and_loss_does_not_mark_returned(): void
    {
        [$staff,$reader,$book] = $this->reconciled();
        $copy = $book->exemplares()->first();
        $copy->update(['condicao' => 'manutencao', 'motivo_condicao' => 'teste']);
        $this->expectException(DomainException::class);
        app(CheckoutExemplar::class)->execute($staff, $reader->id, $book->id, today()->addWeek()->toDateString());
    }

    public function test_loss_closes_loan_without_return_date(): void
    {
        [$staff,$reader,$book] = $this->reconciled();
        $loan = app(CheckoutExemplar::class)->execute($staff, $reader->id, $book->id, today()->addWeek()->toDateString());
        app(CloseLoan::class)->loss($staff, $loan, 'perdido');
        $this->assertNull($loan->fresh()->data_devolvido);
        $this->assertSame('perda', $loan->fresh()->encerramento_motivo);
    }

    public function test_reader_and_inactive_staff_are_denied_by_actions(): void
    {
        [, $reader,$book] = $this->reconciled();
        $inactive = User::factory()->librarian()->inactive()->create();
        $this->expectException(DomainException::class);
        app(CheckoutExemplar::class)->execute($inactive, $reader->id, $book->id, today()->addWeek()->toDateString());
    }

    public function test_closed_legacy_history_keeps_null_copy(): void
    {
        [,$reader,$book] = $this->context();
        $loan = Locacao::create(['usuario_id' => $reader->id, 'livro_id' => $book->id, 'data_locacao' => today()->subMonth(), 'data_devolucao' => today()->subWeeks(3), 'data_devolvido' => today()->subWeeks(2), 'encerrado_em' => today()->subWeeks(2), 'encerramento_motivo' => 'devolucao', 'status' => 'devolvida']);
        $this->assertNull($loan->exemplar_id);
    }

    public function test_backfill_migration_closes_legacy_return_without_inventing_a_copy(): void
    {
        [, $reader, $book] = $this->context();
        $returnedAt = today()->subWeek();
        $loan = Locacao::create([
            'usuario_id' => $reader->id,
            'livro_id' => $book->id,
            'data_locacao' => today()->subMonth(),
            'data_devolucao' => today()->subWeeks(3),
            'data_devolvido' => $returnedAt,
            'status' => 'devolvida',
        ]);

        $migration = require base_path('database/migrations/2026_09_25_000004_backfill_closed_loans_and_restrict_history.php');
        $migration->up();

        $loan->refresh();
        $this->assertTrue($loan->encerrado_em->isSameDay($returnedAt));
        $this->assertSame('devolucao', $loan->encerramento_motivo);
        $this->assertNull($loan->exemplar_id);
        $this->assertNull($loan->active_exemplar_id);
    }

    private function context(array $a = []): array
    {
        $au = Autor::create(['nome' => 'A']);
        $ca = Categoria::create(['nome' => 'C']);
        $book = Livro::create(array_merge(['titulo' => 'T', 'autor_id' => $au->id, 'categoria_id' => $ca->id, 'quantidade_total' => 1, 'quantidade_disponivel' => 1, 'status' => 'ativo'], $a));

        return [User::factory()->librarian()->create(), User::factory()->reader()->create(), $book];
    }

    private function reconciliationPayload(?Locacao $loan, bool $ok): array
    {
        return ['unidades' => [['codigo_patrimonial' => 'P-001', 'condicao' => 'circulacao', 'identificacao_fisica' => true]], 'vinculacoes' => $loan ? [$loan->id => 'P-001'] : [], 'confirmar_divergencia' => $ok, 'motivo' => 'contagem física'];
    }

    private function reconciled(): array
    {
        [$s,$r,$b] = $this->context();
        $this->actingAs($s)->post(route('livros.reconciliacao.store', $b), $this->reconciliationPayload(null, true));

        return [$s, $r, $b->fresh()];
    }
}
