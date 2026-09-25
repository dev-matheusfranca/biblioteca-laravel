<?php

namespace App\Services\Reports;

use App\Enums\ReservationStatus;
use App\Models\Reserva;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class ReservationQueueReport
{
    /** @return Builder<Reserva> */
    public function query(?string $search = null): Builder
    {
        $position = Reserva::query()
            ->from('reservas as anteriores')
            ->selectRaw('COUNT(*)')
            ->whereColumn('anteriores.livro_id', 'reservas.livro_id')
            ->where('anteriores.status', ReservationStatus::Waiting->value)
            ->whereNotNull('anteriores.active_key')
            ->where(function (Builder $query): void {
                $query->whereColumn('anteriores.created_at', '<', 'reservas.created_at')
                    ->orWhere(function (Builder $sameMoment): void {
                        $sameMoment->whereColumn('anteriores.created_at', 'reservas.created_at')
                            ->whereColumn('anteriores.id', '<=', 'reservas.id');
                    });
            });

        return Reserva::query()
            ->join('livros', 'livros.id', '=', 'reservas.livro_id')
            ->select([
                'reservas.id',
                'reservas.livro_id',
                'reservas.created_at',
                'livros.titulo as titulo',
            ])
            ->selectSub($position, 'posicao')
            ->where('reservas.status', ReservationStatus::Waiting->value)
            ->whereNotNull('reservas.active_key')
            ->when($search, fn (Builder $query, string $term) => $query->whereLike('livros.titulo', "%{$term}%"))
            ->orderBy('livros.titulo')
            ->orderBy('reservas.created_at')
            ->orderBy('reservas.id');
    }

    public function waitingHours(Reserva $reservation, CarbonImmutable $generatedAt): int
    {
        return max(0, (int) $reservation->created_at->diffInHours($generatedAt));
    }
}
