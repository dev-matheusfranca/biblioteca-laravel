<?php

namespace App\Actions\Communication;

use App\Enums\OutboxStatus;
use App\Models\CommunicationOutbox;
use App\Models\Reserva;

class CancelReservationAvailabilityCommunication
{
    public function execute(Reserva $reservation): void
    {
        if ($reservation->availability_version < 1) {
            return;
        }

        $event = CommunicationOutbox::query()
            ->where('event_key', "reservation:{$reservation->id}:available:{$reservation->availability_version}")
            ->lockForUpdate()
            ->first();
        if (! $event) {
            return;
        }

        if ($event->status !== OutboxStatus::Sent) {
            $event->forceFill([
                'status' => OutboxStatus::Cancelled,
                'lease_token' => null,
                'leased_at' => null,
                'next_attempt_at' => null,
                'completed_at' => now(),
                'error_code' => 'domain_state_changed',
            ])->save();
        }
        $event->portalNotice()->whereNull('cancelled_at')->update(['cancelled_at' => now()]);
    }
}
