<?php

namespace App\Console\Commands;

use App\Actions\Circulation\AllocateReservationsForBook;
use App\Models\Reserva;
use Illuminate\Console\Command;

class ExpireReservations extends Command
{
    protected $signature = 'reservas:expirar';

    protected $description = 'Expira retiradas vencidas e reconcilia a fila de reservas';

    public function handle(AllocateReservationsForBook $allocator): int
    {
        Reserva::query()
            ->whereNotNull('active_key')
            ->select('livro_id')
            ->distinct()
            ->orderBy('livro_id')
            ->pluck('livro_id')
            ->each(fn (int $bookId) => $allocator->execute($bookId));

        return self::SUCCESS;
    }
}
