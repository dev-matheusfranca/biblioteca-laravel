<?php

namespace Tests\Feature;

use App\Models\Autor;
use App\Models\Categoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PhysicalCatalog;
use Tests\TestCase;

class CatalogAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_catalog_lists_only_active_titles_and_hides_inactive_details(): void
    {
        $author = Autor::create(['nome' => 'Autora pública', 'nacionalidade' => 'Brasileira']);
        $category = Categoria::create(['nome' => 'Literatura']);
        [, , $active] = PhysicalCatalog::book([
            'titulo' => 'Visível',
            'isbn' => '9780132350884',
            'autor_id' => $author->id,
            'categoria_id' => $category->id,
            'quantidade_total' => 2,
            'quantidade_disponivel' => 1,
            'status' => 'ativo',
        ], author: $author, category: $category);
        [, , $inactive] = PhysicalCatalog::book([
            'titulo' => 'Interno',
            'autor_id' => $author->id,
            'categoria_id' => $category->id,
            'quantidade_total' => 1,
            'quantidade_disponivel' => 1,
            'status' => 'inativo',
        ], author: $author, category: $category);

        $this->get(route('catalogo.index'))
            ->assertOk()
            ->assertViewHas('livros', fn ($livros) => $livros->total() === 1 && $livros->first()->is($active))
            ->assertViewHas('categorias', fn ($categorias) => $categorias->contains($category));

        $this->get(route('catalogo.show', $active))->assertOk();
        $this->get(route('catalogo.show', $inactive))->assertNotFound();

        $this->get(route('catalogo.index', ['q' => '9780132350884']))
            ->assertOk()
            ->assertViewHas('livros', fn ($livros) => $livros->total() === 1 && $livros->first()->is($active));
    }
}
