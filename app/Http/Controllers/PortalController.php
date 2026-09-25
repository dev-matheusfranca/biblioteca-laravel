<?php

namespace App\Http\Controllers;

use App\Actions\Circulation\RenewLoan;
use App\Models\Locacao;
use App\Models\PortalNotice;
use App\Models\Reserva;
use DomainException;
use Illuminate\Http\Request;

class PortalController extends Controller
{
    public function index(Request $request)
    {
        $locacoes = Locacao::query()
            ->where('usuario_id', $request->user()->getKey())
            ->with('livro')
            ->orderByDesc('data_locacao')
            ->orderByDesc('id')
            ->paginate(10, ['*'], 'emprestimos_page')
            ->withQueryString();

        $reservas = Reserva::query()
            ->where('usuario_id', $request->user()->getKey())
            ->with(['livro', 'exemplar'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10, ['*'], 'reservas_page')
            ->withQueryString();

        $abertas = Locacao::query()
            ->where('usuario_id', $request->user()->getKey())
            ->where('status', '!=', 'devolvida')
            ->count();

        $avisos = PortalNotice::query()
            ->where('usuario_id', $request->user()->id)
            ->whereNull('read_at')
            ->whereNull('cancelled_at')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        return view('portal.index', compact('locacoes', 'abertas', 'reservas', 'avisos'));
    }

    public function show(Request $request, Locacao $locacao, RenewLoan $renew)
    {
        $this->authorize('view', $locacao);

        $locacao->load(['livro', 'usuario', 'renovacoes']);
        $motivoRenovacao = $renew->blockReason($request->user(), $locacao);
        $podeRenovar = $motivoRenovacao === null;

        return view('portal.show', compact('locacao', 'podeRenovar', 'motivoRenovacao'));
    }

    public function renovar(Request $request, Locacao $locacao, RenewLoan $renew)
    {
        $this->authorize('view', $locacao);
        try {
            $renew->execute($request->user(), $locacao);
        } catch (DomainException $exception) {
            return back()->withErrors(['renovacao' => $exception->getMessage()]);
        }

        return back()->with('success', 'Empréstimo renovado. O novo prazo já está disponível.');
    }
}
