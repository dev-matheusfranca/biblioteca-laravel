<?php

namespace App\Http\Controllers\Api;

use App\Actions\Api\ExecuteIdempotentMutation;
use App\Actions\Api\IdempotentResult;
use App\Actions\Circulation\CancelReservation;
use App\Actions\Circulation\CreateReservation;
use App\Actions\Circulation\RenewLoan;
use App\Http\Controllers\Controller;
use App\Http\Resources\LoanResource;
use App\Http\Resources\ReservationResource;
use App\Models\Livro;
use App\Models\Locacao;
use App\Models\Reserva;
use App\Models\User;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CirculationController extends Controller
{
    public function reserve(Request $request, Livro $livro, ExecuteIdempotentMutation $idempotency, CreateReservation $create): JsonResponse
    {
        abort_unless($livro->status === 'ativo', 404);
        $result = $idempotency->execute($request, $request->user(), function (User $reader) use ($create, $livro, $request) {
            $reservation = $create->execute($reader, $livro->id)->load(['livro.autor', 'livro.categoria', 'exemplar']);

            return ['status' => 201, 'body' => ['data' => (new ReservationResource($reservation))->resolve($request)]];
        });

        return $this->response($result);
    }

    public function cancel(Request $request, Reserva $reserva, ExecuteIdempotentMutation $idempotency, CancelReservation $cancel): JsonResponse
    {
        abort_unless($reserva->usuario_id === $request->user()->id, 404);
        $result = $idempotency->execute($request, $request->user(), function (User $reader) use ($cancel, $reserva) {
            $changed = $cancel->execute($reader, $reserva);
            if (! $changed) {
                throw new DomainException('Esta reserva já estava encerrada.');
            }

            return ['status' => 200, 'body' => ['data' => ['id' => $reserva->id, 'status' => 'cancelada']]];
        });

        return $this->response($result);
    }

    public function renew(Request $request, Locacao $locacao, ExecuteIdempotentMutation $idempotency, RenewLoan $renew): JsonResponse
    {
        abort_unless($locacao->usuario_id === $request->user()->id, 404);
        $result = $idempotency->execute($request, $request->user(), function (User $reader) use ($locacao, $renew, $request) {
            $renew->execute($reader, $locacao);
            $freshLoan = Locacao::query()->with(['livro.autor', 'livro.categoria', 'renovacoes'])->findOrFail($locacao->id);

            return ['status' => 200, 'body' => ['data' => (new LoanResource($freshLoan))->resolve($request)]];
        });

        return $this->response($result);
    }

    private function response(IdempotentResult $result): JsonResponse
    {
        $headers = $result->replayed ? ['Idempotency-Replayed' => 'true'] : [];

        return response()->json($result->body, $result->status, $headers);
    }
}
