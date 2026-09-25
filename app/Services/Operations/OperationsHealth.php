<?php

namespace App\Services\Operations;

use App\Enums\OutboxStatus;
use App\Models\CommunicationOutbox;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Throwable;

class OperationsHealth
{
    public const HEARTBEAT_KEY = 'operations:scheduler:last_heartbeat_at';

    private const HEARTBEAT_MAX_AGE_SECONDS = 180;

    public function heartbeat(): void
    {
        Cache::put(self::HEARTBEAT_KEY, now()->getTimestamp(), now()->addMinutes(10));
    }

    /** @return array{healthy:bool,seconds_ago:int|null} */
    public function schedulerReport(): array
    {
        return $this->scheduler();
    }

    /**
     * @return array{
     *     healthy:bool,
     *     dependencies:array{database:array{healthy:bool},redis_queue:array{healthy:bool,depth:int|null},cache:array{healthy:bool},catalog_cache:array{healthy:bool},scheduler:array{healthy:bool,seconds_ago:int|null}},
     *     outbox:array{pending_count:int|null,oldest_pending_seconds:int|null,actionable_pending_count:int|null,oldest_actionable_pending_seconds:int|null,failed_count:int|null,oldest_failed_seconds:int|null,processing_count:int|null,oldest_processing_seconds:int|null,stale_processing_count:int|null},
     *     alerts:list<string>,
     *     http:array{count:int,errors:int,average_latency_ms:int,max_latency_ms:int,window_minutes:int},
     *     jobs:array{count:int,errors:int,average_latency_ms:int,max_latency_ms:int,window_minutes:int}
     * }
     */
    public function report(bool $includeScheduler = true): array
    {
        $database = $this->database();
        $cache = $this->cache();
        $catalogCache = $this->catalogCache();
        $queue = $this->queue();
        $scheduler = $this->scheduler();
        $outbox = $database['healthy'] ? $this->outbox() : $this->emptyOutbox();
        $alerts = $this->alerts($database, $cache, $catalogCache, $queue, $scheduler, $outbox, $includeScheduler);

        return [
            'healthy' => $alerts === [],
            'dependencies' => [
                'database' => $database,
                'redis_queue' => $queue,
                'cache' => $cache,
                'catalog_cache' => $catalogCache,
                'scheduler' => $scheduler,
            ],
            'outbox' => $outbox,
            'alerts' => $alerts,
            'http' => app(OperationalMetrics::class)->httpSummary(),
            'jobs' => app(OperationalMetrics::class)->jobSummary(),
        ];
    }

    /** @return array{healthy:bool} */
    private function database(): array
    {
        try {
            DB::select('select 1');

            return ['healthy' => true];
        } catch (Throwable) {
            return ['healthy' => false];
        }
    }

    /** @return array{healthy:bool,depth:int|null} */
    private function queue(): array
    {
        try {
            $depth = Queue::connection('redis')->size('communications');

            return ['healthy' => true, 'depth' => max(0, (int) $depth)];
        } catch (Throwable) {
            return ['healthy' => false, 'depth' => null];
        }
    }

    /** @return array{healthy:bool} */
    private function cache(): array
    {
        return $this->probeCache();
    }

    /** @return array{healthy:bool} */
    private function catalogCache(): array
    {
        return $this->probeCache((string) config('catalog.store', 'catalog'));
    }

    /** @return array{healthy:bool} */
    private function probeCache(?string $store = null): array
    {
        $key = 'operations:health:probe:'.Str::uuid();
        $value = (string) Str::uuid();

        try {
            $cache = $store === null ? Cache::store() : Cache::store($store);
            $cache->put($key, $value, 10);

            return ['healthy' => $cache->get($key) === $value];
        } catch (Throwable) {
            return ['healthy' => false];
        } finally {
            try {
                ($store === null ? Cache::store() : Cache::store($store))->forget($key);
            } catch (Throwable) {
                // Health probes must not fail because their temporary key cannot be removed.
            }
        }
    }

    /** @return array{healthy:bool,seconds_ago:int|null} */
    private function scheduler(): array
    {
        try {
            $timestamp = Cache::get(self::HEARTBEAT_KEY);
            if (! is_int($timestamp) && ! ctype_digit((string) $timestamp)) {
                return ['healthy' => false, 'seconds_ago' => null];
            }
            $secondsAgo = max(0, now()->getTimestamp() - (int) $timestamp);

            return ['healthy' => $secondsAgo <= self::HEARTBEAT_MAX_AGE_SECONDS, 'seconds_ago' => $secondsAgo];
        } catch (Throwable) {
            return ['healthy' => false, 'seconds_ago' => null];
        }
    }

    /** @return array{pending_count:int|null,oldest_pending_seconds:int|null,actionable_pending_count:int|null,oldest_actionable_pending_seconds:int|null,failed_count:int|null,oldest_failed_seconds:int|null,processing_count:int|null,oldest_processing_seconds:int|null,stale_processing_count:int|null} */
    private function outbox(): array
    {
        try {
            $pending = CommunicationOutbox::query()->where('status', OutboxStatus::Pending->value);
            $actionablePending = (clone $pending)->where(fn ($query) => $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()));
            $failed = CommunicationOutbox::query()->where('status', OutboxStatus::Failed->value);
            $processing = CommunicationOutbox::query()->where('status', OutboxStatus::Processing->value);
            $staleProcessing = (clone $processing)->where(fn ($query) => $query->whereNull('leased_at')->orWhere('leased_at', '<=', now()->subSeconds(120)));
            $oldestPending = (clone $pending)->min('created_at');
            $oldestActionablePending = (clone $actionablePending)->min('created_at');
            $oldestFailed = (clone $failed)->min('created_at');
            $oldestProcessing = (clone $processing)->min('leased_at');

            return [
                'pending_count' => $pending->count(),
                'oldest_pending_seconds' => $this->ageInSeconds($oldestPending),
                'actionable_pending_count' => $actionablePending->count(),
                'oldest_actionable_pending_seconds' => $this->ageInSeconds($oldestActionablePending),
                'failed_count' => $failed->count(),
                'oldest_failed_seconds' => $this->ageInSeconds($oldestFailed),
                'processing_count' => $processing->count(),
                'oldest_processing_seconds' => $this->ageInSeconds($oldestProcessing),
                'stale_processing_count' => $staleProcessing->count(),
            ];
        } catch (Throwable) {
            return $this->emptyOutbox();
        }
    }

    private function ageInSeconds(mixed $timestamp): ?int
    {
        if ($timestamp === null) {
            return null;
        }

        try {
            return max(0, (int) floor(now()->diffInSeconds(CarbonImmutable::parse($timestamp), true)));
        } catch (Throwable) {
            return null;
        }
    }

    /** @return array{pending_count:int|null,oldest_pending_seconds:int|null,actionable_pending_count:int|null,oldest_actionable_pending_seconds:int|null,failed_count:int|null,oldest_failed_seconds:int|null,processing_count:int|null,oldest_processing_seconds:int|null,stale_processing_count:int|null} */
    private function emptyOutbox(): array
    {
        return [
            'pending_count' => null,
            'oldest_pending_seconds' => null,
            'actionable_pending_count' => null,
            'oldest_actionable_pending_seconds' => null,
            'failed_count' => null,
            'oldest_failed_seconds' => null,
            'processing_count' => null,
            'oldest_processing_seconds' => null,
            'stale_processing_count' => null,
        ];
    }

    /**
     * @param  array{healthy:bool}  $database
     * @param  array{healthy:bool}  $cache
     * @param  array{healthy:bool}  $catalogCache
     * @param  array{healthy:bool,depth:int|null}  $queue
     * @param  array{healthy:bool,seconds_ago:int|null}  $scheduler
     * @param  array{pending_count:int|null,oldest_pending_seconds:int|null,actionable_pending_count:int|null,oldest_actionable_pending_seconds:int|null,failed_count:int|null,oldest_failed_seconds:int|null,processing_count:int|null,oldest_processing_seconds:int|null,stale_processing_count:int|null}  $outbox
     * @return list<string>
     */
    private function alerts(array $database, array $cache, array $catalogCache, array $queue, array $scheduler, array $outbox, bool $includeScheduler): array
    {
        $alerts = [];
        if (! $database['healthy']) {
            $alerts[] = 'database_unavailable';
            $alerts[] = 'outbox_unavailable';
        }
        if (! $cache['healthy']) {
            $alerts[] = 'operational_cache_unavailable';
        }
        if (! $catalogCache['healthy']) {
            $alerts[] = 'catalog_cache_unavailable';
        }
        if (! $queue['healthy']) {
            $alerts[] = 'redis_queue_unavailable';
        }
        if ($includeScheduler && ! $scheduler['healthy']) {
            $alerts[] = 'scheduler_stale';
        }
        if (($outbox['failed_count'] ?? 0) > 0) {
            $alerts[] = 'outbox_failed';
        }
        if (($outbox['oldest_actionable_pending_seconds'] ?? 0) > 120) {
            $alerts[] = 'outbox_pending_overdue';
        }
        if (($outbox['stale_processing_count'] ?? 0) > 0) {
            $alerts[] = 'outbox_processing_stale';
        }

        return $alerts;
    }
}
