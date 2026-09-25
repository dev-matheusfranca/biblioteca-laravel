<?php

namespace Tests\Feature;

use App\Models\Autor;
use App\Models\Categoria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PhysicalCatalog;
use Tests\TestCase;

class LoanEligibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_reader_cannot_receive_a_loan(): void
    {
        $librarian = User::factory()->librarian()->create();
        $inactiveReader = User::factory()->reader()->inactive()->create();
        $author = Autor::create(['nome' => 'Autor', 'nacionalidade' => 'Brasileira']);
        $category = Categoria::create(['nome' => 'Romance']);
        [, , $book] = PhysicalCatalog::book([
            'titulo' => 'Elegibilidade',
            'autor_id' => $author->id,
            'categoria_id' => $category->id,
            'quantidade_total' => 1,
            'quantidade_disponivel' => 1,
            'status' => 'ativo',
        ], author: $author, category: $category);

        $this->actingAs($librarian)->post(route('locacoes.store'), [
            'usuario_id' => $inactiveReader->id,
            'livro_id' => $book->id,
            'data_devolucao' => now()->addWeek()->toDateString(),
        ])->assertSessionHasErrors('usuario_id');

        $this->assertDatabaseMissing('locacoes', ['usuario_id' => $inactiveReader->id, 'livro_id' => $book->id]);
        $this->assertDatabaseHas('livros', ['id' => $book->id, 'quantidade_disponivel' => 1]);
    }
}
