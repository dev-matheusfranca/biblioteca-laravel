<?php

namespace App\Http\Controllers;

use App\Http\Requests\Categoria\CategoriaIndexRequest;
use App\Http\Requests\Categoria\CategoriaRequest;
use App\Models\Categoria;
use Illuminate\Support\Facades\DB;

class CategoriaController extends Controller
{
    public function index(CategoriaIndexRequest $request)
    {
        $categorias = Categoria::query()
            ->withCount('livros')
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = '%'.$request->string('q')->trim().'%';

                $query->where(function ($query) use ($search) {
                    $query->where('nome', 'like', $search)
                        ->orWhere('descricao', 'like', $search);
                });
            })
            ->orderBy('nome')
            ->orderBy('id')
            ->paginate(10)
            ->withQueryString();

        return view('categorias.index', compact('categorias'));
    }

    public function create()
    {
        return view('categorias.create');
    }

    public function store(CategoriaRequest $request)
    {
        Categoria::create($request->validated());

        return redirect()->route('categorias.index')->with('success', 'Categoria criada.');
    }

    public function show(Categoria $categoria)
    {
        $livros = $categoria->livros()
            ->with(['autor', 'categoria'])
            ->orderBy('titulo')
            ->orderBy('id')
            ->paginate(10, ['*'], 'livros_page')
            ->withQueryString();

        return view('categorias.show', compact('categoria', 'livros'));
    }

    public function edit(Categoria $categoria)
    {
        return view('categorias.edit', compact('categoria'));
    }

    public function update(CategoriaRequest $request, Categoria $categoria)
    {
        $categoria->update($request->validated());

        return redirect()->route('categorias.index')->with('success', 'Categoria atualizada.');
    }

    public function destroy(Categoria $categoria)
    {
        return DB::transaction(function () use ($categoria) {
            $categoria = Categoria::query()->lockForUpdate()->findOrFail($categoria->getKey());

            if ($categoria->livros()->exists()) {
                return redirect()->route('categorias.index')
                    ->with('error', 'Não é possível remover uma categoria que possui livros cadastrados.');
            }

            $categoria->delete();

            return redirect()->route('categorias.index')->with('success', 'Categoria removida.');
        });
    }
}
