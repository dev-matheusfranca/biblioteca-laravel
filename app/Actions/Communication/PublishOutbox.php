<?php

namespace App\Actions\Communication;

use App\Enums\OutboxStatus;
use App\Jobs\DeliverCommunication;
use App\Models\CommunicationOutbox;
use Illuminate\Support\Facades\Log;
use Throwable;

class PublishOutbox
{
    public function execute(int $limit = 100): int
    {
        $ids = CommunicationOutbox::query()
            ->where(function ($query) {
                $query->where(function ($pending) {
                    $pending->where('status', OutboxStatus::Pending->value)
                        ->where(fn ($due) => $due->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
                        ->where(fn ($enqueue) => $enqueue->whereNull('last_enqueued_at')->orWhere('last_enqueued_at', '<=', now()->subMinute()));
                })->orWhere(function ($processing) {
                    $processing->where('status', OutboxStatus::Processing->value)
                        ->where('leased_at', '<=', now()->subMinutes(2));
                });
            })
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        return $ids->sum(fn (int $id): int => $this->publishOne($id) ? 1 : 0);
    }

    public function publishOne(int $outboxId, bool $force = false): bool
    {
        $event = CommunicationOutbox::query()->find($outboxId);
        if (! $event || $event->status->isFinal()) {
            return false;
        }
        if (! $force && $event->next_attempt_at?->isFuture()) {
            return false;
        }

        try {
            DeliverCommunication::dispatch($event->id, $event->correlation_id);
            CommunicationOutbox::query()
                ->whereKey($event->id)
                ->whereIn('status', [OutboxStatus::Pending->value, OutboxStatus::Processing->value])
                ->update(['last_enqueued_at' => now(), 'error_code' => null, 'updated_at' => now()]);

            return true;
        } catch (Throwable) {
            CommunicationOutbox::query()
                ->whereKey($event->id)
                ->whereIn('status', [OutboxStatus::Pending->value, OutboxStatus::Processing->value])
                ->update(['status' => OutboxStatus::Pending->value, 'next_attempt_at' => now()->addMinute(), 'error_code' => 'queue_dispatch_failed', 'updated_at' => now()]);
            Log::warning('Outbox dispatch failed', ['outbox_id' => $event->id, 'correlation_id' => $event->correlation_id, 'error_code' => 'queue_dispatch_failed']);

            return false;
        }
    }
}
