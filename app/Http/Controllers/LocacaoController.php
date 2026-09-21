<?php

namespace App\Http\Controllers;

use App\Http\Requests\Locacao\LocacaoIndexRequest;
use App\Http\Requests\Locacao\StoreLocacaoRequest;
use App\Models\Livro;
use App\Models\Locacao;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LocacaoController extends Controller
{
    public function index(LocacaoIndexRequest $request)
    {
        $locacoes = Locacao::query()
            ->with(['usuario', 'livro'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = '%'.$request->string('q')->trim().'%';

                $query->where(function ($query) use ($search) {
                    $query->whereHas('usuario', fn ($query) => $query->where('name', 'like', $search))
                        ->orWhereHas('livro', fn ($query) => $query->where('titulo', 'like', $search));
                });
            })
            ->when($request->input('status') === 'ativa', function ($query) {
                $query->where('status', '!=', 'devolvida');
            })
            ->when($request->input('status') === 'devolvida', function ($query) {
                $query->where('status', 'devolvida');
            })
            ->when($request->input('status') === 'atrasada', function ($query) {
                $query->where('status', '!=', 'devolvida')
                    ->whereDate('data_devolucao', '<', today());
            })
            ->orderByDesc('data_locacao')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return view('locacoes.index', compact('locacoes'));
    }

    public function create()
    {
        $usuarios = User::query()->orderBy('name')->orderBy('id')->get();
        $livros = Livro::query()
            ->where('status', 'ativo')
            ->where('quantidade_disponivel', '>', 0)
            ->orderBy('titulo')
            ->orderBy('id')
            ->get();

        return view('locacoes.create', compact('usuarios', 'livros'));
    }

    public function store(StoreLocacaoRequest $request)
    {
        $data = $request->validated();

        return DB::transaction(function () use ($data) {
            $livro = Livro::lockForUpdate()->findOrFail($data['livro_id']);

            if ($livro->status !== 'ativo' || $livro->quantidade_disponivel <= 0) {
                return back()->withErrors(['livro_id' => 'Livro indisponível para empréstimo.'])->withInput();
            }

            $already = Locacao::where('usuario_id', $data['usuario_id'])
                ->where('livro_id', $data['livro_id'])
                ->where('status', '!=', 'devolvida')
                ->first();

            if ($already) {
                return back()->withErrors(['usuario_id' => 'O usuário já possui este livro emprestado.'])->withInput();
            }

            Locacao::create([
                'usuario_id' => $data['usuario_id'],
                'livro_id' => $data['livro_id'],
                'data_locacao' => now()->toDateString(),
                'data_devolucao' => $data['data_devolucao'],
                'status' => 'ativa',
            ]);

            $livro->decrement('quantidade_disponivel');

            return redirect()->route('locacoes.index')->with('success', 'Empréstimo criado.');
        });
    }

    public function show(Locacao $locacao)
    {
        $locacao->load(['usuario', 'livro']);

        return view('locacoes.show', compact('locacao'));
    }

    public function devolver(Locacao $locacao)
    {
        return DB::transaction(function () use ($locacao) {
            $locacao = Locacao::query()->lockForUpdate()->findOrFail($locacao->getKey());

            if ($locacao->status === 'devolvida') {
                return redirect()->route('locacoes.index')->with('error', 'Este empréstimo já foi devolvido.');
            }

            $livro = Livro::query()->lockForUpdate()->findOrFail($locacao->livro_id);
            $livro->quantidade_disponivel = min(
                $livro->quantidade_total,
                $livro->quantidade_disponivel + 1
            );
            $livro->save();

            $locacao->update([
                'status' => 'devolvida',
                'data_devolvido' => now()->toDateString(),
            ]);

            return redirect()->route('locacoes.index')->with('success', 'Empréstimo devolvido com sucesso.');
        });
    }
}
