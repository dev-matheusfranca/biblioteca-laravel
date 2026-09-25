<?php

namespace App\Http\Controllers;

use App\Actions\Circulation\AllocateReservationsForBook;
use App\Http\Requests\Livro\LivroIndexRequest;
use App\Http\Requests\Livro\LivroRequest;
use App\Models\Autor;
use App\Models\Categoria;
use App\Models\Livro;
use App\Models\Locacao;
use Illuminate\Support\Facades\DB;

class LivroController extends Controller
{
    public function index(LivroIndexRequest $request)
    {
        $livros = Livro::query()
            ->with(['autor', 'categoria'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = '%'.$request->string('q')->trim().'%';

                $query->where(function ($query) use ($search) {
                    $query->where('titulo', 'like', $search)
                        ->orWhere('isbn', 'like', $search)
                        ->orWhereHas('autor', fn ($query) => $query->where('nome', 'like', $search));
                });
            })
            ->when($request->filled('categoria'), function ($query) use ($request) {
                $query->where('categoria_id', $request->integer('categoria'));
            })
            ->when($request->input('disponibilidade') === 'disponivel', function ($query) {
                $query->where('status', 'ativo')
                    ->where('modo_acervo', 'exemplares')
                    ->where('quantidade_disponivel', '>', 0);
            })
            ->when($request->input('disponibilidade') === 'indisponivel', function ($query) {
                $query->where(function ($query) {
                    $query->where('quantidade_disponivel', '<=', 0)
                        ->orWhere('modo_acervo', '!=', 'exemplares')
                        ->orWhere('status', '!=', 'ativo');
                });
            })
            ->orderBy('titulo')
            ->orderBy('id')
            ->paginate(10)
            ->withQueryString();

        $categorias = Categoria::query()->orderBy('nome')->orderBy('id')->get();

        return view('livros.index', compact('livros', 'categorias'));
    }

    public function create()
    {
        $autores = Autor::query()->orderBy('nome')->orderBy('id')->get();
        $categorias = Categoria::query()->orderBy('nome')->orderBy('id')->get();

        return view('livros.create', compact('autores', 'categorias'));
    }

    public function store(LivroRequest $request)
    {
        $data = $request->validated();

        $data['quantidade_disponivel'] = $data['quantidade_total'];
        Livro::create($data);

        return redirect()->route('livros.index')->with('success', 'Livro criado.');
    }

    public function show(Livro $livro)
    {
        $livro->load(['autor', 'categoria']);
        $locacoes = $livro->locacoes()
            ->with('usuario')
            ->orderByDesc('data_locacao')
            ->orderByDesc('id')
            ->paginate(10, ['*'], 'emprestimos_page')
            ->withQueryString();

        return view('livros.show', compact('livro', 'locacoes'));
    }

    public function edit(Livro $livro)
    {
        $autores = Autor::all();
        $categorias = Categoria::all();

        return view('livros.edit', compact('livro', 'autores', 'categorias'));
    }

    public function update(LivroRequest $request, Livro $livro)
    {
        $data = $request->validated();

        return DB::transaction(function () use ($livro, $data) {
            $livro = Livro::query()->lockForUpdate()->findOrFail($livro->getKey());
            if ($livro->usaExemplares() && (int) $data['quantidade_total'] !== $livro->quantidade_total) {
                return back()->withErrors(['quantidade_total' => 'Após a reconciliação, a quantidade é derivada dos exemplares e não pode ser editada manualmente.'])->withInput();
            }
            $emprestados = Locacao::query()
                ->where('livro_id', $livro->id)
                ->where('status', '!=', 'devolvida')
                ->count();

            if ($data['quantidade_total'] < $emprestados) {
                return back()->withErrors([
                    'quantidade_total' => "A quantidade total não pode ser menor que os {$emprestados} exemplar(es) emprestado(s).",
                ])->withInput();
            }

            if ($livro->usaExemplares()) {
                unset($data['quantidade_total']);
            } else {
                $data['quantidade_disponivel'] = $data['quantidade_total'] - $emprestados;
            }
            $livro->update($data);
            if ($livro->usaExemplares()) {
                app(AllocateReservationsForBook::class)->executeLocked($livro);
            }

            return redirect()->route('livros.index')->with('success', 'Livro atualizado.');
        });
    }

    public function destroy(Livro $livro)
    {
        return DB::transaction(function () use ($livro) {
            $livro = Livro::query()->lockForUpdate()->findOrFail($livro->getKey());

            if ($livro->locacoes()->exists() || $livro->exemplares()->exists()) {
                return redirect()->route('livros.index')
                    ->with('error', 'Não é possível remover um livro que possui histórico de empréstimos ou exemplares. Inative o cadastro.');
            }

            $livro->delete();

            return redirect()->route('livros.index')->with('success', 'Livro removido.');
        });
    }
}
