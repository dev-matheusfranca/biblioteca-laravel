<?php

namespace App\Http\Controllers;

use App\Http\Requests\Autor\AutorIndexRequest;
use App\Http\Requests\Autor\AutorRequest;
use App\Models\Autor;
use Illuminate\Support\Facades\DB;

class AutorController extends Controller
{
    public function index(AutorIndexRequest $request)
    {
        $autores = Autor::query()
            ->withCount('livros')
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = '%'.$request->string('q')->trim().'%';

                $query->where(function ($query) use ($search) {
                    $query->where('nome', 'like', $search)
                        ->orWhere('nacionalidade', 'like', $search);
                });
            })
            ->orderBy('nome')
            ->orderBy('id')
            ->paginate(10)
            ->withQueryString();

        return view('autores.index', compact('autores'));
    }

    public function create()
    {
        return view('autores.create');
    }

    public function store(AutorRequest $request)
    {
        Autor::create($request->validated());

        return redirect()->route('autores.index')->with('success', 'Autor criado com sucesso.');
    }

    public function show(Autor $autor)
    {
        $livros = $autor->livros()
            ->with(['autor', 'categoria'])
            ->orderBy('titulo')
            ->orderBy('id')
            ->paginate(10, ['*'], 'livros_page')
            ->withQueryString();

        return view('autores.show', compact('autor', 'livros'));
    }

    public function edit(Autor $autor)
    {
        return view('autores.edit', compact('autor'));
    }

    public function update(AutorRequest $request, Autor $autor)
    {
        $autor->update($request->validated());

        return redirect()->route('autores.index')->with('success', 'Autor atualizado.');
    }

    public function destroy(Autor $autor)
    {
        return DB::transaction(function () use ($autor) {
            $autor = Autor::query()->lockForUpdate()->findOrFail($autor->getKey());

            if ($autor->livros()->exists()) {
                return redirect()->route('autores.index')
                    ->with('error', 'Não é possível remover um autor que possui livros cadastrados.');
            }

            $autor->delete();

            return redirect()->route('autores.index')->with('success', 'Autor removido.');
        });
    }
}
