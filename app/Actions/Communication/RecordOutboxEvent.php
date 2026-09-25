<?php

namespace App\Actions\Communication;

use App\Enums\OutboxStatus;
use App\Enums\OutboxType;
use App\Models\CommunicationOutbox;
use App\Models\Locacao;
use App\Models\Reserva;
use App\Services\CorrelationId;

class RecordOutboxEvent
{
    public function __construct(private readonly CorrelationId $correlations) {}

    public function reservationAvailable(Reserva $reservation): CommunicationOutbox
    {
        $version = $reservation->availability_version;

        return CommunicationOutbox::query()->firstOrCreate(
            ['event_key' => "reservation:{$reservation->id}:available:{$version}"],
            [
                'type' => OutboxType::ReservationAvailable,
                'usuario_id' => $reservation->usuario_id,
                'reserva_id' => $reservation->id,
                'payload' => [
                    'reservation_id' => $reservation->id,
                    'book_id' => $reservation->livro_id,
                    'user_id' => $reservation->usuario_id,
                    'availability_version' => $version,
                ],
                'status' => OutboxStatus::Pending,
                'correlation_id' => $this->correlations->current(),
            ],
        );
    }

    public function loanReminder(Locacao $loan, OutboxType $type, string $dueDate, string $correlationId): CommunicationOutbox
    {
        $kind = $type === OutboxType::LoanDueSoon ? 'due-soon' : 'overdue';

        return CommunicationOutbox::query()->firstOrCreate(
            ['event_key' => "loan:{$loan->id}:{$dueDate}:{$kind}"],
            [
                'type' => $type,
                'usuario_id' => $loan->usuario_id,
                'locacao_id' => $loan->id,
                'payload' => [
                    'loan_id' => $loan->id,
                    'book_id' => $loan->livro_id,
                    'user_id' => $loan->usuario_id,
                    'due_date' => $dueDate,
                    'kind' => $kind,
                ],
                'status' => OutboxStatus::Pending,
                'correlation_id' => $correlationId,
            ],
        );
    }
}
