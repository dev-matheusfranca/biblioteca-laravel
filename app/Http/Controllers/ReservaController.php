<?php

namespace App\Http\Controllers;

use App\Actions\Circulation\CancelReservation;
use App\Actions\Circulation\CreateReservation;
use App\Models\Livro;
use App\Models\Reserva;
use DomainException;
use Illuminate\Http\Request;

class ReservaController extends Controller
{
    public function index()
    {
        $reservas = Reserva::query()
            ->with(['usuario', 'livro', 'exemplar'])
            ->orderByRaw('CASE WHEN active_key IS NOT NULL THEN 0 ELSE 1 END')
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate(15);

        return view('reservas.index', compact('reservas'));
    }

    public function store(Request $request, Livro $livro, CreateReservation $create)
    {
        try {
            $create->execute($request->user(), $livro->id);
        } catch (DomainException $exception) {
            return back()->withErrors(['reserva' => $exception->getMessage()]);
        }

        return redirect()->route('portal.index')->with('success', 'Reserva registrada. A fila e o prazo de retirada aparecerão na sua conta.');
    }

    public function destroy(Request $request, Reserva $reserva, CancelReservation $cancel)
    {
        return $this->cancel($request, $reserva, $cancel, false);
    }

    public function cancelar(Request $request, Reserva $reserva, CancelReservation $cancel)
    {
        return $this->cancel($request, $reserva, $cancel, true);
    }

    private function cancel(Request $request, Reserva $reserva, CancelReservation $cancel, bool $staff)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);
        try {
            $changed = $cancel->execute($request->user(), $reserva, $data['reason'] ?? null);
        } catch (DomainException $exception) {
            abort(403, $exception->getMessage());
        }

        return redirect()->route($staff ? 'reservas.index' : 'portal.index')->with(
            $changed ? 'success' : 'error',
            $changed ? 'Reserva cancelada.' : 'Esta reserva já estava encerrada.'
        );
    }
}
