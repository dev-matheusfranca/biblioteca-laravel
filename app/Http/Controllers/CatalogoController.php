<?php

namespace App\Http\Controllers;

use App\Http\Requests\Catalogo\CatalogoIndexRequest;
use App\Models\Livro;
use App\Models\Reserva;
use App\Services\Catalog\PublicCatalog;
use App\Services\CirculationEligibility;
use App\Services\CurrentCirculationPolicy;
use Illuminate\Http\Request;

class CatalogoController extends Controller
{
    public function index(CatalogoIndexRequest $request, PublicCatalog $catalog)
    {
        return view('catalogo.index', $catalog->page(
            (string) $request->input('q', ''),
            $request->filled('categoria') ? $request->integer('categoria') : null,
            $request->integer('page', 1),
        ));
    }

    public function show(Request $request, Livro $livro, CurrentCirculationPolicy $policies, CirculationEligibility $eligibility)
    {
        abort_unless($livro->status === 'ativo', 404);

        $livro->load(['autor', 'categoria']);
        $reservaAtual = null;
        $podeReservar = false;
        $motivoReserva = null;
        if ($request->user()) {
            $reservaAtual = Reserva::query()
                ->where('active_key', Reserva::activeKey($request->user()->id, $livro->id))
                ->first();
            if (! $reservaAtual) {
                $motivoReserva = $eligibility->reservationBlockReason($request->user(), $livro, $policies->get());
                $podeReservar = $motivoReserva === null;
            }
        }

        return view('catalogo.show', compact('livro', 'reservaAtual', 'podeReservar', 'motivoReserva'));
    }
}
