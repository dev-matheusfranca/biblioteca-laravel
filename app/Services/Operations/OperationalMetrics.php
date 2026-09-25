<?php

namespace App\Services\Operations;

use Illuminate\Support\Facades\Cache;
use Throwable;

class OperationalMetrics
{
    private const MAX_MINUTES = 60;

    private const TTL_MINUTES = 70;

    /** @param int<0, max> $durationMs */
    public function recordHttp(int $durationMs, int $status): void
    {
        $this->record('http', $durationMs, $status >= 400 ? 1 : 0);
    }

    /** @param int<0, max>|null $durationMs */
    public function recordJob(string $job, string $outcome, ?int $durationMs): void
    {
        $label = in_array($job, ['communication_delivery'], true) ? $job : 'other';
        $error = in_array($outcome, ['failed_attempt', 'failed_terminal'], true) ? 1 : 0;

        $this->record('job:'.$label.':'.$outcome, $durationMs ?? 0, $error);
    }

    /** @return array{count:int,errors:int,average_latency_ms:int,max_latency_ms:int,window_minutes:int} */
    public function httpSummary(int $minutes = 15): array
    {
        return $this->summary('http', $minutes);
    }

    /** @return array{count:int,errors:int,average_latency_ms:int,max_latency_ms:int,window_minutes:int} */
    public function jobSummary(int $minutes = 15): array
    {
        $summary = $this->emptySummary($minutes);

        foreach ($this->minutes($minutes) as $minute) {
            foreach (['communication_delivery:completed', 'communication_delivery:failed_attempt', 'communication_delivery:failed_terminal'] as $label) {
                $summary = $this->merge($summary, $this->bucket('job:'.$label, $minute));
            }
        }

        return $this->finalize($summary);
    }

    /** @return array{count:int,errors:int,latency_total_ms:int,max_latency_ms:int} */
    private function bucket(string $type, string $minute): array
    {
        try {
            $value = Cache::get($this->bucketKey($type, $minute));
        } catch (Throwable) {
            return $this->emptyBucket();
        }

        if (! is_array($value)) {
            return $this->emptyBucket();
        }

        return [
            'count' => max(0, (int) ($value['count'] ?? 0)),
            'errors' => max(0, (int) ($value['errors'] ?? 0)),
            'latency_total_ms' => max(0, (int) ($value['latency_total_ms'] ?? 0)),
            'max_latency_ms' => max(0, (int) ($value['max_latency_ms'] ?? 0)),
        ];
    }

    private function record(string $type, int $durationMs, int $errors): void
    {
        $minute = now()->utc()->format('YmdHi');
        $lock = null;

        try {
            $lock = Cache::lock('operations:metrics:lock:'.$type.':'.$minute, 2);
            if (! $lock->get()) {
                return;
            }
            $bucket = $this->bucket($type, $minute);
            $bucket['count']++;
            $bucket['errors'] += $errors;
            $bucket['latency_total_ms'] += max(0, $durationMs);
            $bucket['max_latency_ms'] = max($bucket['max_latency_ms'], max(0, $durationMs));
            Cache::put($this->bucketKey($type, $minute), $bucket, now()->addMinutes(self::TTL_MINUTES));
        } catch (Throwable) {
            // Observability must never delay or fail the observed operation.
        } finally {
            if ($lock !== null) {
                try {
                    $lock->release();
                } catch (Throwable) {
                    // The lock has an expiry and no business action depends on its release.
                }
            }
        }
    }

    /** @return array{count:int,errors:int,average_latency_ms:int,max_latency_ms:int,window_minutes:int} */
    private function summary(string $type, int $minutes): array
    {
        $summary = $this->emptySummary($minutes);
        foreach ($this->minutes($minutes) as $minute) {
            $summary = $this->merge($summary, $this->bucket($type, $minute));
        }

        return $this->finalize($summary);
    }

    /** @return list<string> */
    private function minutes(int $minutes): array
    {
        $window = min(self::MAX_MINUTES, max(1, $minutes));
        $current = now()->utc()->startOfMinute();
        $result = [];
        for ($offset = 0; $offset < $window; $offset++) {
            $result[] = $current->copy()->subMinutes($offset)->format('YmdHi');
        }

        return $result;
    }

    /** @return array{count:int,errors:int,latency_total_ms:int,max_latency_ms:int} */
    private function emptyBucket(): array
    {
        return ['count' => 0, 'errors' => 0, 'latency_total_ms' => 0, 'max_latency_ms' => 0];
    }

    /** @return array{count:int,errors:int,latency_total_ms:int,max_latency_ms:int,window_minutes:int} */
    private function emptySummary(int $minutes): array
    {
        return $this->emptyBucket() + ['window_minutes' => min(self::MAX_MINUTES, max(1, $minutes))];
    }

    /** @param array{count:int,errors:int,latency_total_ms:int,max_latency_ms:int,window_minutes:int} $summary
     * @param  array{count:int,errors:int,latency_total_ms:int,max_latency_ms:int}  $bucket
     * @return array{count:int,errors:int,latency_total_ms:int,max_latency_ms:int,window_minutes:int}
     */
    private function merge(array $summary, array $bucket): array
    {
        $summary['count'] += $bucket['count'];
        $summary['errors'] += $bucket['errors'];
        $summary['latency_total_ms'] += $bucket['latency_total_ms'];
        $summary['max_latency_ms'] = max($summary['max_latency_ms'], $bucket['max_latency_ms']);

        return $summary;
    }

    /** @param array{count:int,errors:int,latency_total_ms:int,max_latency_ms:int,window_minutes:int} $summary
     * @return array{count:int,errors:int,average_latency_ms:int,max_latency_ms:int,window_minutes:int}
     */
    private function finalize(array $summary): array
    {
        return [
            'count' => $summary['count'],
            'errors' => $summary['errors'],
            'average_latency_ms' => $summary['count'] === 0 ? 0 : (int) round($summary['latency_total_ms'] / $summary['count']),
            'max_latency_ms' => $summary['max_latency_ms'],
            'window_minutes' => $summary['window_minutes'],
        ];
    }

    private function bucketKey(string $type, string $minute): string
    {
        return 'operations:metrics:v1:'.$type.':'.$minute;
    }
}
