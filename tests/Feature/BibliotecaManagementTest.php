<?php

namespace Tests\Feature;

use App\Models\Autor;
use App\Models\Categoria;
use App\Models\Livro;
use App\Models\Locacao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class BibliotecaManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_library_management(): void
    {
        foreach ([
            route('autores.index'),
            route('categorias.index'),
            route('livros.index'),
            route('locacoes.index'),
        ] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }

    public function test_guest_home_exposes_only_active_catalog_statistics(): void
    {
        [$autor, $categoria, $livroAtivo] = $this->catalogo([
            'quantidade_total' => 3,
            'quantidade_disponivel' => 2,
        ]);
        Livro::create([
            'titulo' => 'Livro Inativo',
            'autor_id' => $autor->id,
            'categoria_id' => $categoria->id,
            'quantidade_total' => 5,
            'quantidade_disponivel' => 5,
            'status' => 'inativo',
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertViewIs('home')
            ->assertViewHas('livros', fn ($livros) => $livros->count() === 1 && $livros->first()->is($livroAtivo))
            ->assertViewHas('stats', fn ($stats) => $stats === [
                'titulos' => 1,
                'exemplares' => 3,
                'disponiveis' => 2,
                'autores' => 1,
                'categorias' => 1,
            ])
            ->assertViewHas('devolucoes', fn ($devolucoes) => $devolucoes->isEmpty());
    }

    public function test_authenticated_home_exposes_full_catalog_and_open_rental_statistics(): void
    {
        $operator = User::factory()->create();
        [$autor, $categoria, $livroAtivo] = $this->catalogo([
            'quantidade_total' => 3,
            'quantidade_disponivel' => 1,
        ]);
        Livro::create([
            'titulo' => 'Livro Inativo',
            'autor_id' => $autor->id,
            'categoria_id' => $categoria->id,
            'quantidade_total' => 5,
            'quantidade_disponivel' => 5,
            'status' => 'inativo',
        ]);
        $aberto = $this->locacao(User::factory()->create(), $livroAtivo, [
            'data_devolucao' => Carbon::yesterday()->toDateString(),
        ]);
        $this->locacao(User::factory()->create(), $livroAtivo, [
            'status' => 'devolvida',
            'data_devolvido' => Carbon::today()->toDateString(),
        ]);

        $this->actingAs($operator)->get(route('home'))
            ->assertOk()
            ->assertViewIs('home')
            ->assertViewHas('livros', fn ($livros) => $livros->count() === 2)
            ->assertViewHas('stats', fn ($stats) => $stats === [
                'titulos' => 2,
                'exemplares' => 8,
                'disponiveis' => 1,
                'autores' => 1,
                'categorias' => 1,
                'emprestimos' => 1,
                'atrasados' => 1,
            ])
            ->assertViewHas('devolucoes', fn ($devolucoes) => $devolucoes->count() === 1 && $devolucoes->first()->is($aberto));
    }

    public function test_authenticated_user_can_render_every_registered_management_view(): void
    {
        $user = User::factory()->create();
        [$autor, $categoria, $livro] = $this->catalogo();
        $locacao = $this->locacao($user, $livro);

        $this->actingAs($user);

        $this->get(route('autores.index'))->assertOk()->assertViewIs('autores.index');
        $this->get(route('autores.create'))->assertOk()->assertViewIs('autores.create');
        $this->get(route('autores.show', $autor))->assertOk()->assertViewIs('autores.show');
        $this->get(route('autores.edit', $autor))->assertOk()->assertViewIs('autores.edit');
        $this->get(route('categorias.index'))->assertOk()->assertViewIs('categorias.index');
        $this->get(route('categorias.create'))->assertOk()->assertViewIs('categorias.create');
        $this->get(route('categorias.show', $categoria))->assertOk()->assertViewIs('categorias.show');
        $this->get(route('categorias.edit', $categoria))->assertOk()->assertViewIs('categorias.edit');
        $this->get(route('livros.index'))->assertOk()->assertViewIs('livros.index');
        $this->get(route('livros.create'))->assertOk()->assertViewIs('livros.create');
        $this->get(route('livros.show', $livro))->assertOk()->assertViewIs('livros.show');
        $this->get(route('livros.edit', $livro))->assertOk()->assertViewIs('livros.edit');
        $this->get(route('locacoes.index'))->assertOk()->assertViewIs('locacoes.index');
        $this->get(route('locacoes.create'))->assertOk()->assertViewIs('locacoes.create');
        $this->get(route('locacoes.show', $locacao))->assertOk()->assertViewIs('locacoes.show');
    }

    public function test_book_onboarding_explains_missing_catalog_and_disables_submission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('livros.create'))
            ->assertOk()
            ->assertSee('Prepare o catálogo antes de cadastrar um livro.')
            ->assertSee('Cadastrar autor')
            ->assertSee('Cadastrar categoria')
            ->assertSee('type="submit" class="btn btn-primary" disabled', false);
    }

    public function test_book_status_can_be_changed_and_only_valid_active_books_can_be_rented(): void
    {
        $operator = User::factory()->create();
        $reader = User::factory()->create();
        [$autor, $categoria, $livro] = $this->catalogo(['quantidade_total' => 2, 'quantidade_disponivel' => 2]);
        $payload = [
            'titulo' => $livro->titulo,
            'autor_id' => $autor->id,
            'categoria_id' => $categoria->id,
            'quantidade_total' => 2,
            'isbn' => $livro->isbn,
        ];
        $emprestimo = [
            'usuario_id' => $reader->id,
            'livro_id' => $livro->id,
            'data_devolucao' => Carbon::tomorrow()->toDateString(),
        ];

        $this->actingAs($operator)->put(route('livros.update', $livro), [...$payload, 'status' => 'inativo'])
            ->assertRedirect(route('livros.index'));
        $this->assertDatabaseHas('livros', ['id' => $livro->id, 'status' => 'inativo']);

        $this->actingAs($operator)->post(route('locacoes.store'), $emprestimo)
            ->assertSessionHasErrors('livro_id');

        $this->actingAs($operator)->put(route('livros.update', $livro), [...$payload, 'status' => 'ativo'])
            ->assertRedirect(route('livros.index'));
        $this->assertDatabaseHas('livros', ['id' => $livro->id, 'status' => 'ativo']);

        $this->actingAs($operator)->post(route('locacoes.store'), $emprestimo)
            ->assertRedirect(route('locacoes.index'))
            ->assertSessionHas('success', 'Empréstimo criado.');

        $this->actingAs($operator)->put(route('livros.update', $livro), [...$payload, 'status' => 'arquivado'])
            ->assertSessionHasErrors('status');
        $this->assertDatabaseHas('livros', ['id' => $livro->id, 'status' => 'ativo']);
    }

    public function test_catalog_filters_and_counts_are_applied_and_kept_in_pagination_links(): void
    {
        $user = User::factory()->create();
        [$autor, $categoria, $livroDisponivel] = $this->catalogo([
            'titulo' => 'Clean Code',
            'isbn' => '9780132350884',
            'quantidade_total' => 2,
            'quantidade_disponivel' => 2,
        ]);
        $livroIndisponivel = Livro::create([
            'titulo' => 'Domain-Driven Design',
            'autor_id' => $autor->id,
            'categoria_id' => $categoria->id,
            'quantidade_total' => 1,
            'quantidade_disponivel' => 0,
            'status' => 'ativo',
        ]);
        $livroInativoComSaldo = Livro::create([
            'titulo' => 'Livro Inativo Com Saldo',
            'autor_id' => $autor->id,
            'categoria_id' => $categoria->id,
            'quantidade_total' => 2,
            'quantidade_disponivel' => 2,
            'status' => 'inativo',
        ]);
        $outraCategoria = Categoria::create(['nome' => 'História']);
        Livro::create([
            'titulo' => 'Outro Título',
            'autor_id' => $autor->id,
            'categoria_id' => $outraCategoria->id,
            'quantidade_total' => 1,
            'quantidade_disponivel' => 1,
            'status' => 'ativo',
        ]);
        foreach (range(1, 10) as $numero) {
            Livro::create([
                'titulo' => "Clean {$numero}",
                'autor_id' => $autor->id,
                'categoria_id' => $categoria->id,
                'quantidade_total' => 1,
                'quantidade_disponivel' => 1,
                'status' => 'ativo',
            ]);
        }

        $response = $this->actingAs($user)->get(route('livros.index', [
            'q' => 'clean',
            'categoria' => $categoria->id,
            'disponibilidade' => 'disponivel',
        ]));

        $response->assertOk()
            ->assertViewHas('livros')
            ->assertViewHas('categorias', fn ($categorias) => $categorias->contains($categoria))
            ->assertSee('q=clean', false)
            ->assertDontSee($livroIndisponivel->titulo);

        $livrosFiltrados = $response->viewData('livros');
        $this->assertSame(11, $livrosFiltrados->total());

        $this->actingAs($user)->get(route('livros.index', [
            'q' => 'clean',
            'categoria' => $categoria->id,
            'disponibilidade' => 'disponivel',
            'page' => 2,
        ]))->assertViewHas('livros', fn ($livros) => $livros->first()->is($livroDisponivel));

        $this->actingAs($user)->get(route('livros.index', [
            'q' => 'Inativo',
            'disponibilidade' => 'disponivel',
        ]))->assertViewHas('livros', fn ($livros) => $livros->isEmpty());

        $this->actingAs($user)->get(route('livros.index', [
            'q' => 'Inativo',
            'disponibilidade' => 'indisponivel',
        ]))->assertViewHas('livros', fn ($livros) => $livros->total() === 1 && $livros->first()->is($livroInativoComSaldo));

        $this->actingAs($user)->get(route('livros.index', ['disponibilidade' => 'indisponivel']))
            ->assertViewHas('livros', fn ($livros) => $livros->total() === 2
                && $livros->pluck('id')->contains($livroIndisponivel->id)
                && $livros->pluck('id')->contains($livroInativoComSaldo->id));

        $this->actingAs($user)->get(route('autores.index', ['q' => $autor->nome]))
            ->assertViewHas('autores', fn ($autores) => $autores->first()->livros_count === 14);
        $this->actingAs($user)->get(route('categorias.index', ['q' => $categoria->nome]))
            ->assertViewHas('categorias', fn ($categorias) => $categorias->first()->livros_count === 13);
    }

    public function test_rental_filters_treat_active_as_all_open_and_calculate_overdue_by_due_date(): void
    {
        $user = User::factory()->create(['name' => 'Maria Leitora']);
        [, , $livro] = $this->catalogo(['titulo' => 'Livro Atrasado']);
        $atrasada = $this->locacao($user, $livro, [
            'status' => 'ativa',
            'data_devolucao' => Carbon::yesterday()->toDateString(),
        ]);
        $devolvida = $this->locacao(User::factory()->create(), $livro, [
            'status' => 'devolvida',
            'data_devolucao' => Carbon::tomorrow()->toDateString(),
            'data_devolvido' => Carbon::today()->toDateString(),
        ]);

        $this->actingAs($user)->get(route('locacoes.index', ['status' => 'ativa']))
            ->assertViewHas('locacoes', fn ($locacoes) => $locacoes->total() === 1 && $locacoes->first()->is($atrasada));
        $this->actingAs($user)->get(route('locacoes.index', ['status' => 'atrasada']))
            ->assertViewHas('locacoes', fn ($locacoes) => $locacoes->total() === 1 && $locacoes->first()->is($atrasada));
        $this->actingAs($user)->get(route('locacoes.index', ['status' => 'devolvida', 'q' => $livro->titulo]))
            ->assertViewHas('locacoes', fn ($locacoes) => $locacoes->total() === 1 && $locacoes->first()->is($devolvida));
    }

    public function test_only_active_available_books_can_be_rented_and_open_rentals_cannot_be_duplicated(): void
    {
        $operator = User::factory()->create();
        $reader = User::factory()->create();
        [, , $livro] = $this->catalogo(['quantidade_total' => 2, 'quantidade_disponivel' => 2]);

        $payload = [
            'usuario_id' => $reader->id,
            'livro_id' => $livro->id,
            'data_devolucao' => Carbon::tomorrow()->toDateString(),
        ];

        $this->actingAs($operator)->post(route('locacoes.store'), $payload)
            ->assertRedirect(route('locacoes.index'))
            ->assertSessionHas('success', 'Empréstimo criado.');

        $this->assertDatabaseHas('locacoes', [
            'usuario_id' => $reader->id,
            'livro_id' => $livro->id,
            'status' => 'ativa',
        ]);
        $this->assertDatabaseHas('livros', ['id' => $livro->id, 'quantidade_disponivel' => 1]);

        Locacao::query()->where('usuario_id', $reader->id)->where('livro_id', $livro->id)->update([
            'status' => 'atrasada',
            'data_devolucao' => Carbon::yesterday()->toDateString(),
        ]);

        $this->actingAs($operator)->post(route('locacoes.store'), $payload)
            ->assertSessionHasErrors('usuario_id');

        $livroInativo = Livro::create([
            'titulo' => 'Indisponível',
            'autor_id' => $livro->autor_id,
            'categoria_id' => $livro->categoria_id,
            'quantidade_total' => 1,
            'quantidade_disponivel' => 1,
            'status' => 'inativo',
        ]);
        $this->actingAs($operator)->post(route('locacoes.store'), [...$payload, 'livro_id' => $livroInativo->id])
            ->assertSessionHasErrors('livro_id');
    }

    public function test_return_is_idempotent_and_does_not_increase_stock_twice(): void
    {
        $operator = User::factory()->create();
        [, , $livro] = $this->catalogo(['quantidade_total' => 1, 'quantidade_disponivel' => 0]);
        $locacao = $this->locacao(User::factory()->create(), $livro);

        $this->actingAs($operator)->post(route('locacoes.devolver', $locacao))
            ->assertRedirect(route('locacoes.index'))
            ->assertSessionHas('success', 'Empréstimo devolvido com sucesso.');
        $this->assertDatabaseHas('locacoes', ['id' => $locacao->id, 'status' => 'devolvida']);
        $this->assertDatabaseHas('livros', ['id' => $livro->id, 'quantidade_disponivel' => 1]);

        $this->actingAs($operator)->post(route('locacoes.devolver', $locacao))
            ->assertRedirect(route('locacoes.index'))
            ->assertSessionHas('error', 'Este empréstimo já foi devolvido.');
        $this->assertDatabaseHas('livros', ['id' => $livro->id, 'quantidade_disponivel' => 1]);
    }

    public function test_catalog_entities_with_dependencies_cannot_be_deleted_and_rental_deletion_route_does_not_exist(): void
    {
        $user = User::factory()->create();
        [$autor, $categoria, $livro] = $this->catalogo();
        $this->locacao(User::factory()->create(), $livro);

        $this->actingAs($user)->delete(route('autores.destroy', $autor))
            ->assertSessionHas('error', 'Não é possível remover um autor que possui livros cadastrados.');
        $this->actingAs($user)->delete(route('categorias.destroy', $categoria))
            ->assertSessionHas('error', 'Não é possível remover uma categoria que possui livros cadastrados.');
        $this->actingAs($user)->delete(route('livros.destroy', $livro))
            ->assertSessionHas('error', 'Não é possível remover um livro que possui histórico de empréstimos.');

        $this->assertDatabaseHas('autores', ['id' => $autor->id]);
        $this->assertDatabaseHas('categorias', ['id' => $categoria->id]);
        $this->assertDatabaseHas('livros', ['id' => $livro->id]);
        $this->assertFalse(Route::has('locacoes.destroy'));
    }

    public function test_book_stock_cannot_be_reduced_below_open_rentals_and_is_recalculated_after_update(): void
    {
        $operator = User::factory()->create();
        [$autor, $categoria, $livro] = $this->catalogo(['quantidade_total' => 3, 'quantidade_disponivel' => 1]);
        $this->locacao(User::factory()->create(), $livro);
        $this->locacao(User::factory()->create(), $livro, ['status' => 'atrasada']);

        $payload = [
            'titulo' => $livro->titulo,
            'autor_id' => $autor->id,
            'categoria_id' => $categoria->id,
            'quantidade_total' => 1,
            'isbn' => $livro->isbn,
        ];

        $this->actingAs($operator)->put(route('livros.update', $livro), $payload)
            ->assertSessionHasErrors('quantidade_total');
        $this->assertDatabaseHas('livros', ['id' => $livro->id, 'quantidade_total' => 3, 'quantidade_disponivel' => 1]);

        $this->actingAs($operator)->put(route('livros.update', $livro), [...$payload, 'quantidade_total' => 2])
            ->assertRedirect(route('livros.index'));
        $this->assertDatabaseHas('livros', ['id' => $livro->id, 'quantidade_total' => 2, 'quantidade_disponivel' => 0]);
    }

    /** @return array{Autor, Categoria, Livro} */
    private function catalogo(array $livroAttributes = []): array
    {
        $autor = Autor::create(['nome' => 'Autor Exemplo', 'nacionalidade' => 'Brasileira']);
        $categoria = Categoria::create(['nome' => 'Tecnologia']);
        $livro = Livro::create(array_merge([
            'titulo' => 'Livro Exemplo',
            'autor_id' => $autor->id,
            'categoria_id' => $categoria->id,
            'quantidade_total' => 3,
            'quantidade_disponivel' => 3,
            'status' => 'ativo',
        ], $livroAttributes));

        return [$autor, $categoria, $livro];
    }

    private function locacao(User $user, Livro $livro, array $attributes = []): Locacao
    {
        return Locacao::create(array_merge([
            'usuario_id' => $user->id,
            'livro_id' => $livro->id,
            'data_locacao' => Carbon::today()->toDateString(),
            'data_devolucao' => Carbon::tomorrow()->toDateString(),
            'status' => 'ativa',
        ], $attributes));
    }
}
