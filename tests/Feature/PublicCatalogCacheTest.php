<?php

namespace Tests\Feature;

use App\Models\Livro;
use App\Services\Catalog\PublicCatalog;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Support\PhysicalCatalog;
use Tests\TestCase;

class PublicCatalogCacheTest extends TestCase
{
    use DatabaseMigrations;

    public function runDatabaseMigrations(): void
    {
        $this->refreshTestDatabase();
        config(['catalog.store' => 'array', 'catalog.revision_store' => 'array']);
        $this->beforeApplicationDestroyed(function () {
            RefreshDatabaseState::$migrated = false;
        });
    }

    public function test_cache_reuses_public_queries_and_invalidates_only_after_commit(): void
    {
        [, , $book] = PhysicalCatalog::book(['titulo' => 'Título original']);
        $catalog = app(PublicCatalog::class);
        DB::enableQueryLog();
        $catalog->page();
        $coldCount = count(DB::getQueryLog());
        DB::flushQueryLog();
        $this->assertSame('Título original', $catalog->page()['livros']->first()->titulo);
        $this->assertLessThan($coldCount, count(DB::getQueryLog()));
        $this->assertSame('hit', request()->attributes->get('catalog_cache'));
        $revision = Cache::store('array')->get('catalog:revision');

        DB::beginTransaction();
        $book->update(['titulo' => 'Título revertido']);
        $this->assertSame($revision, Cache::store('array')->get('catalog:revision'));
        $this->assertSame('Título revertido', $catalog->page()['livros']->first()->titulo);
        DB::rollBack();
        $this->assertSame('Título original', $catalog->page()['livros']->first()->titulo);
        $this->assertSame($revision, Cache::store('array')->get('catalog:revision'));

        DB::transaction(fn () => Livro::findOrFail($book->id)->update(['titulo' => 'Título confirmado']));
        $this->assertNotSame($revision, Cache::store('array')->get('catalog:revision'));
        $this->assertSame('Título confirmado', $catalog->page()['livros']->first()->titulo);
    }

    public function test_author_category_status_and_availability_changes_invalidate_public_data(): void
    {
        [$author, $category, $book] = PhysicalCatalog::book();
        $catalog = app(PublicCatalog::class);
        $catalog->page();
        $author->update(['nome' => 'Autoria corrigida']);
        $category->update(['nome' => 'Categoria corrigida']);
        $book->update(['quantidade_disponivel' => 0]);
        $page = $catalog->page();
        $this->assertSame('Autoria corrigida', $page['livros']->first()->autor->nome);
        $this->assertSame('Categoria corrigida', $page['categorias']->first()->nome);
        $this->assertSame(0, (int) $page['livros']->first()->quantidade_disponivel);
        $book->update(['status' => 'inativo']);
        $this->assertSame(0, $catalog->page()['livros']->total());
    }

    public function test_cache_failure_falls_back_and_filtered_pages_do_not_mix(): void
    {
        [$author, $category, $book] = PhysicalCatalog::book(['titulo' => 'Primeiro']);
        PhysicalCatalog::book(['titulo' => 'Segundo']);
        $catalog = app(PublicCatalog::class);
        $this->assertSame(1, $catalog->page('Primeiro', $category->id)['livros']->total());
        $this->assertSame(2, $catalog->page()['livros']->total());
        $this->assertSame(0, $catalog->page('', null, 2)['livros']->count());
        config(['catalog.store' => 'unconfigured-store']);
        $this->assertSame(2, $catalog->page()['livros']->total());
        $this->assertSame('unavailable', request()->attributes->get('catalog_cache'));
    }

    public function test_cached_pagination_uses_the_current_host_and_drops_unrecognized_parameters(): void
    {
        [$author, $category] = PhysicalCatalog::book();
        for ($number = 0; $number < 13; $number++) {
            PhysicalCatalog::book(['titulo' => 'Título '.$number], author: $author, category: $category);
        }
        $this->get('http://localhost/catalogo?unexpected=private-value')->assertOk();
        $this->get('http://library.test/catalogo?unexpected=private-value')
            ->assertOk()->assertViewHas('livros', fn ($books) => str_starts_with($books->url(2), 'http://library.test/catalogo')
                && ! str_contains($books->url(2), 'private-value'));
        $this->get('/catalogo?page=-1')->assertRedirect();
    }
}
