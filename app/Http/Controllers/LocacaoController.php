<?php

namespace App\Http\Controllers;

use App\Actions\Circulation\CheckoutExemplar;
use App\Actions\Circulation\CloseLoan;
use App\Actions\Circulation\RenewLoan;
use App\Enums\UserRole;
use App\Http\Requests\Locacao\LocacaoIndexRequest;
use App\Http\Requests\Locacao\StoreLocacaoRequest;
use App\Models\Livro;
use App\Models\Locacao;
use App\Models\User;
use App\Services\CurrentCirculationPolicy;
use DomainException;
use Illuminate\Http\Request;

class LocacaoController extends Controller
{
    public function index(LocacaoIndexRequest $request)
    {
        $locacoes = Locacao::query()->with(['usuario', 'livro', 'exemplar'])
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($search) => $search->whereHas('usuario', fn ($u) => $u->where('name', 'like', '%'.$request->string('q')->trim().'%'))->orWhereHas('livro', fn ($b) => $b->where('titulo', 'like', '%'.$request->string('q')->trim().'%'))))
            ->when($request->input('status') === 'ativa', fn ($q) => $q->whereNull('encerrado_em'))
            ->when($request->input('status') === 'devolvida', fn ($q) => $q->whereNotNull('encerrado_em')->where('encerramento_motivo', 'devolucao'))
            ->when($request->input('status') === 'atrasada', fn ($q) => $q->whereNull('encerrado_em')->whereDate('data_devolucao', '<', today()))
            ->orderByDesc('data_locacao')->paginate(10)->withQueryString();

        return view('locacoes.index', compact('locacoes'));
    }

    public function create(CurrentCirculationPolicy $policies)
    {
        $usuarios = User::query()->where('role', UserRole::Reader->value)->where('is_active', true)->orderBy('name')->get();
        $livros = Livro::query()
            ->where('status', 'ativo')
            ->where('modo_acervo', 'exemplares')
            ->withCount(['reservas as reservas_ativas_count' => fn ($query) => $query->whereNotNull('active_key')])
            ->orderBy('titulo')
            ->get();
        $policy = $policies->get();

        return view('locacoes.create', compact('usuarios', 'livros', 'policy'));
    }

    public function store(StoreLocacaoRequest $request, CheckoutExemplar $checkout)
    {
        $data = $request->validated();
        try {
            $checkout->execute($request->user(), $data['usuario_id'], $data['livro_id'], (string) ($data['data_devolucao'] ?? ''), $data['exemplar_id'] ?? null);
        } catch (DomainException $e) {
            return back()->withErrors([str_contains(mb_strtolower($e->getMessage()), 'leitor') ? 'usuario_id' : 'livro_id' => $e->getMessage()])->withInput();
        }

        return redirect()->route('locacoes.index')->with('success', 'Empréstimo criado.');
    }

    public function show(Locacao $locacao, RenewLoan $renew)
    {
        $locacao->load(['usuario', 'livro', 'exemplar', 'renovacoes']);
        $motivoRenovacao = $renew->blockReason(request()->user(), $locacao);
        $podeRenovar = $motivoRenovacao === null;

        return view('locacoes.show', compact('locacao', 'podeRenovar', 'motivoRenovacao'));
    }

    public function devolver(Request $request, Locacao $locacao, CloseLoan $closeLoan)
    {
        $ok = $closeLoan->return($request->user(), $locacao);

        return redirect()->route('locacoes.index')->with($ok ? 'success' : 'error', $ok ? 'Empréstimo devolvido com sucesso.' : 'Este empréstimo já foi encerrado.');
    }

    public function encerrarPorPerda(Request $request, Locacao $locacao, CloseLoan $closeLoan)
    {
        $data = $request->validate(['motivo' => ['required', 'string', 'max:1000']]);
        try {
            $ok = $closeLoan->loss($request->user(), $locacao, $data['motivo']);
        } catch (DomainException $e) {
            return back()->withErrors(['motivo' => $e->getMessage()]);
        }

        return redirect()->route('locacoes.index')->with($ok ? 'success' : 'error', $ok ? 'Empréstimo encerrado por perda.' : 'Este empréstimo já foi encerrado.');
    }

    public function renovar(Request $request, Locacao $locacao, RenewLoan $renew)
    {
        try {
            $renew->execute($request->user(), $locacao);
        } catch (DomainException $exception) {
            return back()->withErrors(['renovacao' => $exception->getMessage()]);
        }

        return back()->with('success', 'Empréstimo renovado.');
    }
}
