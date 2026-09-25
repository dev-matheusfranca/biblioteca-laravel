<?php

namespace App\Services\Catalog;

use App\Models\Categoria;
use App\Models\Livro;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class PublicCatalog
{
    /** @return Builder<Livro> */
    public function query(string $search = '', ?int $category = null): Builder
    {
        return Livro::query()
            ->select(['id', 'titulo', 'isbn', 'autor_id', 'categoria_id', 'modo_acervo', 'quantidade_disponivel'])
            ->where('status', 'ativo')
            ->with(['autor:id,nome', 'categoria:id,nome'])
            ->when($search !== '', function (Builder $query) use ($search) {
                $pattern = '%'.$search.'%';
                $query->where(fn (Builder $query) => $query->where('titulo', 'like', $pattern)
                    ->orWhere('isbn', 'like', $pattern)
                    ->orWhereHas('autor', fn (Builder $query) => $query->where('nome', 'like', $pattern)));
            })
            ->when($category, fn (Builder $query) => $query->where('categoria_id', $category))
            ->orderBy('titulo')->orderBy('id');
    }

    public function page(string $search = '', ?int $category = null, int $page = 1): array
    {
        $search = trim($search);
        $page = max(1, min(100000, $page));
        $payload = null;
        // A transaction must not publish uncommitted data to a shared cache.
        if (config('catalog.enabled') && DB::transactionLevel() === 0) {
            try {
                $revisionStore = Cache::store(config('catalog.revision_store'));
                $revision = $revisionStore->get('catalog:revision');
                if ($revision === null) {
                    $revisionStore->add('catalog:revision', (string) Str::uuid(), now()->addDays(30));
                    $revision = $revisionStore->get('catalog:revision');
                }
                $key = 'catalog:page:'.hash('sha256', json_encode([$revision, $search, $category, $page], JSON_THROW_ON_ERROR));
                $store = Cache::store(config('catalog.store'));
                $payload = $store->get($key);
                request()->attributes->set('catalog_cache', $payload === null ? 'miss' : 'hit');
                if ($payload === null) {
                    $payload = $this->fetch($search, $category, $page);
                    $store->put($key, $payload, (int) config('catalog.ttl_seconds'));
                }
            } catch (Throwable) {
                request()->attributes->set('catalog_cache', 'unavailable');
                Log::warning('catalog.cache_unavailable', ['request_id' => request()->attributes->get('request_id')]);
            }
        }
        $payload ??= $this->fetch($search, $category, $page);

        // URLs and query strings are rebuilt per request; neither sessions nor hosts are cached.
        $books = new LengthAwarePaginator($payload['items'], $payload['total'], 12, $page, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
            'query' => array_filter(['q' => $search, 'categoria' => $category], fn ($value) => $value !== '' && $value !== null),
        ]);

        return ['livros' => $books, 'categorias' => $payload['categories']];
    }

    private function fetch(string $search, ?int $category, int $page): array
    {
        $query = $this->query($search, $category);

        return [
            'total' => (clone $query)->toBase()->getCountForPagination(),
            'items' => $query->forPage($page, 12)->get(),
            'categories' => Categoria::query()->select(['id', 'nome'])
                ->whereHas('livros', fn (Builder $query) => $query->where('status', 'ativo'))
                ->orderBy('nome')->orderBy('id')->get(),
        ];
    }

    public function invalidate(): void
    {
        try {
            Cache::store(config('catalog.revision_store'))->put('catalog:revision', (string) Str::uuid(), now()->addDays(30));
        } catch (Throwable) {
            // A committed circulation operation remains successful. Old hints expire within 60 seconds.
            Log::warning('catalog.invalidation_failed', ['request_id' => request()->attributes->get('request_id')]);
        }
    }
}
