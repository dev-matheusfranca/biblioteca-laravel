<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PaginationRequest;
use App\Http\Resources\LoanResource;
use App\Http\Resources\ReservationResource;
use App\Http\Resources\UserResource;
use App\Models\Locacao;
use App\Models\Reserva;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function show(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function loans(PaginationRequest $request)
    {
        $loans = Locacao::query()
            ->where('usuario_id', $request->user()->id)
            ->with(['livro.autor', 'livro.categoria'])
            ->orderByDesc('data_locacao')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return LoanResource::collection($loans);
    }

    public function loan(Request $request, Locacao $locacao): LoanResource
    {
        abort_unless($locacao->usuario_id === $request->user()->id, 404);

        return new LoanResource($locacao->load(['livro.autor', 'livro.categoria', 'renovacoes']));
    }

    public function reservations(PaginationRequest $request)
    {
        $reservations = Reserva::query()
            ->where('usuario_id', $request->user()->id)
            ->with(['livro.autor', 'livro.categoria', 'exemplar'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return ReservationResource::collection($reservations);
    }
}
