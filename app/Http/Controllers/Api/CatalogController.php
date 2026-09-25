<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CatalogIndexRequest;
use App\Http\Resources\LivroResource;
use App\Models\Livro;

class CatalogController extends Controller
{
    public function index(CatalogIndexRequest $request)
    {
        $books = Livro::query()
            ->where('status', 'ativo')
            ->with(['autor', 'categoria'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = '%'.$request->string('q')->trim().'%';

                $query->where(function ($query) use ($search) {
                    $query->where('titulo', 'like', $search)
                        ->orWhere('isbn', 'like', $search)
                        ->orWhereHas('autor', fn ($query) => $query->where('nome', 'like', $search));
                });
            })
            ->when($request->filled('categoria'), fn ($query) => $query->where('categoria_id', $request->integer('categoria')))
            ->orderBy('titulo')
            ->orderBy('id')
            ->paginate($request->integer('per_page', 12))
            ->withQueryString();

        return LivroResource::collection($books);
    }

    public function show(Livro $livro): LivroResource
    {
        abort_unless($livro->status === 'ativo', 404);

        return new LivroResource($livro->load(['autor', 'categoria']));
    }
}
