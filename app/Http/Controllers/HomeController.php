<?php

namespace App\Http\Controllers;

use App\Models\Autor;
use App\Models\Categoria;
use App\Models\Livro;
use App\Models\Locacao;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        $catalogo = Livro::query()->when(! $request->user(), fn ($query) => $query->where('status', 'ativo'));
        $livros = (clone $catalogo)->with(['autor', 'categoria'])->latest('id')->take(6)->get();
        $stats = [
            'titulos' => (clone $catalogo)->count(),
            'exemplares' => (int) (clone $catalogo)->sum('quantidade_total'),
            'disponiveis' => (int) Livro::where('status', 'ativo')->sum('quantidade_disponivel'),
            'autores' => Autor::count(),
            'categorias' => Categoria::count(),
        ];
        $devolucoes = collect();
        if ($request->user()) {
            $abertos = Locacao::whereIn('status', ['ativa', 'atrasada']);
            $stats['emprestimos'] = (clone $abertos)->count();
            $stats['atrasados'] = (clone $abertos)->whereDate('data_devolucao', '<', today())->count();
            $devolucoes = $abertos->with(['livro', 'usuario'])->orderBy('data_devolucao')->orderBy('id')->take(5)->get();
        }

        return view('home', compact('livros', 'stats', 'devolucoes'));
    }
}
