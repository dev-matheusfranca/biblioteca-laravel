<?php

namespace App\Jobs;

use App\Actions\Communication\DeliverOutboxEvent;
use App\Services\Operations\OperationalMetrics;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class DeliverCommunication implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 20;

    public bool $failOnTimeout = true;

    public readonly string $deliveryToken;

    public function __construct(public readonly int $outboxId, public readonly string $correlationId, ?string $deliveryToken = null)
    {
        $this->deliveryToken = $deliveryToken ?? (string) Str::uuid();
        $this->onQueue('communications');
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [5, 30, 120];
    }

    public function handle(DeliverOutboxEvent $deliver): void
    {
        $startedAt = microtime(true);

        try {
            $deliver->execute($this->outboxId, $this->deliveryToken);
            $this->recordOutcome('completed', $startedAt);
        } catch (Throwable $exception) {
            $this->recordOutcome('failed_attempt', $startedAt);
            Log::warning('communication.job.failed_attempt', [
                'outbox_id' => $this->outboxId,
                'correlation_id' => $this->correlationId,
                'outcome' => 'failed_attempt',
            ]);

            throw new RuntimeException('Communication delivery failed; correlation_id='.$this->correlationId);
        }
    }

    public function failed(?Throwable $exception): void
    {
        app(DeliverOutboxEvent::class)->markFailed($this->outboxId, $this->deliveryToken);
        app(OperationalMetrics::class)->recordJob('communication_delivery', 'failed_terminal', null);
        Log::warning('communication.job.failed_terminal', [
            'outbox_id' => $this->outboxId,
            'correlation_id' => $this->correlationId,
            'outcome' => 'failed_terminal',
        ]);
    }

    private function recordOutcome(string $outcome, float $startedAt): void
    {
        $durationMs = max(0, (int) ((microtime(true) - $startedAt) * 1000));
        app(OperationalMetrics::class)->recordJob('communication_delivery', $outcome, $durationMs);
        Log::log($outcome === 'completed' ? 'info' : 'warning', 'communication.job.outcome', [
            'outbox_id' => $this->outboxId,
            'correlation_id' => $this->correlationId,
            'duration_ms' => $durationMs,
            'outcome' => $outcome,
        ]);
    }
}
