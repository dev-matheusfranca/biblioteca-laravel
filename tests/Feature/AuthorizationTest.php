<?php

namespace Tests\Feature;

use App\Models\Autor;
use App\Models\Categoria;
use App\Models\Locacao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PhysicalCatalog;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_ignores_privileged_payload(): void
    {
        $this->post(route('register.post'), [
            'nome' => 'Leitora Pública',
            'email' => 'leitora@example.test',
            'password' => 'senha-segura',
            'password_confirmation' => 'senha-segura',
            'role' => 'admin',
            'is_active' => false,
        ])->assertRedirect(route('portal.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'leitora@example.test',
            'role' => 'leitor',
            'is_active' => true,
        ]);
    }

    public function test_reader_cannot_access_library_management_or_team_management(): void
    {
        $reader = User::factory()->reader()->create();

        foreach ([
            route('autores.index'),
            route('categorias.index'),
            route('livros.index'),
            route('locacoes.index'),
            route('leitores.index'),
            route('equipe.index'),
        ] as $url) {
            $this->actingAs($reader)->get($url)->assertForbidden();
        }
    }

    public function test_librarian_can_access_library_management_but_not_team_management(): void
    {
        $librarian = User::factory()->librarian()->create();

        $this->actingAs($librarian)->get(route('autores.index'))->assertOk();
        $this->actingAs($librarian)->get(route('equipe.index'))->assertForbidden();
        $this->actingAs($librarian)->patch(route('equipe.update', User::factory()->reader()->create()), [
            'role' => 'bibliotecario',
            'is_active' => true,
        ])->assertForbidden();
    }

    public function test_reader_cannot_view_another_readers_loan_in_the_portal(): void
    {
        $reader = User::factory()->reader()->create();
        $otherReader = User::factory()->reader()->create();
        $loan = $this->loanFor($otherReader);

        $this->actingAs($reader)
            ->get(route('portal.emprestimos.show', $loan))
            ->assertForbidden();
    }

    public function test_staff_uses_management_instead_of_another_readers_personal_portal(): void
    {
        $loan = $this->loanFor(User::factory()->reader()->create());

        foreach ([User::factory()->librarian()->create(), User::factory()->admin()->create()] as $staff) {
            $this->actingAs($staff)->get(route('portal.emprestimos.show', $loan))->assertForbidden();
            $this->actingAs($staff)->get(route('locacoes.show', $loan))->assertOk();
        }
    }

    public function test_reader_can_view_its_own_loan_in_the_portal(): void
    {
        $reader = User::factory()->reader()->create();
        $loan = $this->loanFor($reader);

        $this->actingAs($reader)
            ->get(route('portal.emprestimos.show', $loan))
            ->assertOk()
            ->assertViewHas('locacao', fn (Locacao $locacao) => $locacao->is($loan));
    }

    public function test_reader_cannot_mutate_management_resources_or_register_a_return(): void
    {
        $reader = User::factory()->reader()->create();
        [$author, $category] = $this->catalogContext();
        [, , $book] = PhysicalCatalog::book([
            'titulo' => 'Gestão protegida',
            'autor_id' => $author->id,
            'categoria_id' => $category->id,
            'quantidade_total' => 1,
            'quantidade_disponivel' => 1,
            'status' => 'ativo',
        ], author: $author, category: $category);
        $loan = PhysicalCatalog::loan($reader, $book, [
            'data_devolucao' => now()->addWeek()->toDateString(),
        ]);

        $this->actingAs($reader)->post(route('autores.store'), [])->assertForbidden();
        $this->actingAs($reader)->put(route('livros.update', $book), [])->assertForbidden();
        $this->actingAs($reader)->delete(route('autores.destroy', $author))->assertForbidden();
        $this->actingAs($reader)->post(route('locacoes.devolver', $loan))->assertForbidden();
    }

    public function test_inactive_account_cannot_login_and_existing_session_is_revoked(): void
    {
        $inactive = User::factory()->inactive()->create();

        $this->post(route('login.post'), [
            'email' => $inactive->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->actingAs($inactive)
            ->get(route('portal.index'))
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_reader_home_keeps_only_public_catalog_context(): void
    {
        $reader = User::factory()->reader()->create();
        [$autor, $categoria] = $this->catalogContext();
        PhysicalCatalog::book([
            'titulo' => 'Disponível',
            'autor_id' => $autor->id,
            'categoria_id' => $categoria->id,
            'quantidade_total' => 2,
            'quantidade_disponivel' => 2,
            'status' => 'ativo',
        ], author: $autor, category: $categoria);
        PhysicalCatalog::book([
            'titulo' => 'Interno',
            'autor_id' => $autor->id,
            'categoria_id' => $categoria->id,
            'quantidade_total' => 1,
            'quantidade_disponivel' => 1,
            'status' => 'inativo',
        ], author: $autor, category: $categoria);

        $this->actingAs($reader)->get(route('home'))
            ->assertOk()
            ->assertViewHas('livros', fn ($livros) => $livros->count() === 1)
            ->assertViewHas('stats', fn ($stats) => ! array_key_exists('emprestimos', $stats))
            ->assertViewHas('devolucoes', fn ($devolucoes) => $devolucoes->isEmpty());
    }

    /** @return array{Autor, Categoria} */
    private function catalogContext(): array
    {
        return [
            Autor::create(['nome' => 'Autora', 'nacionalidade' => 'Brasileira']),
            Categoria::create(['nome' => 'Tecnologia']),
        ];
    }

    private function loanFor(User $reader): Locacao
    {
        [$autor, $categoria] = $this->catalogContext();
        [, , $book] = PhysicalCatalog::book([
            'titulo' => 'Livro protegido',
            'autor_id' => $autor->id,
            'categoria_id' => $categoria->id,
            'quantidade_total' => 1,
            'quantidade_disponivel' => 1,
            'status' => 'ativo',
        ], author: $autor, category: $categoria);

        return PhysicalCatalog::loan($reader, $book, [
            'data_devolucao' => now()->addWeek()->toDateString(),
        ]);
    }
}
