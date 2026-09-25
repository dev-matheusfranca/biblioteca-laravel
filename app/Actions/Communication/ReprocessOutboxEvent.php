<?php

namespace App\Actions\Communication;

use App\Enums\OutboxStatus;
use App\Models\AuditLog;
use App\Models\CommunicationOutbox;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class ReprocessOutboxEvent
{
    public function __construct(private readonly PublishOutbox $publisher) {}

    public function execute(User $actor, CommunicationOutbox $outbox): void
    {
        $outboxId = $outbox->id;
        DB::transaction(function () use ($actor, $outboxId) {
            $event = CommunicationOutbox::query()->lockForUpdate()->findOrFail($outboxId);
            if (! $actor->fresh()?->isAdmin()) {
                throw new DomainException('Somente administradores ativos podem reprocessar comunicações.');
            }
            if ($event->status !== OutboxStatus::Failed) {
                throw new DomainException('Somente uma comunicação com falha terminal pode ser reprocessada.');
            }
            $event->forceFill([
                'status' => OutboxStatus::Pending,
                'attempts' => 0,
                'lease_token' => null,
                'leased_at' => null,
                'last_enqueued_at' => null,
                'next_attempt_at' => null,
                'completed_at' => null,
                'error_code' => null,
            ])->save();
            AuditLog::create([
                'action' => 'communication.reprocessed',
                'actor_id' => $actor->id,
                'subject_id' => $event->usuario_id,
                'metadata' => ['outbox_id' => $event->id, 'event_key' => $event->event_key, 'correlation_id' => $event->correlation_id],
            ]);
        }, 3);

        $this->publisher->publishOne($outboxId, force: true);
    }
}
