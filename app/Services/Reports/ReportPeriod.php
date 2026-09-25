<?php

namespace App\Services\Reports;

use Carbon\CarbonImmutable;

final readonly class ReportPeriod
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public CarbonImmutable $reference,
    ) {}

    /** @param array<string, mixed> $validated */
    public static function from(array $validated): self
    {
        $timezone = (string) config('app.timezone', 'America/Sao_Paulo');
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $end = self::date($validated['period_end'] ?? null, $today, $timezone);
        $start = self::date($validated['period_start'] ?? null, $end->subDays(29), $timezone);
        $reference = self::date($validated['reference_date'] ?? null, $end, $timezone);

        return new self($start, $end, $reference);
    }

    public function startDate(): string
    {
        return $this->start->toDateString();
    }

    public function endDate(): string
    {
        return $this->end->toDateString();
    }

    public function periodStart(): string
    {
        return $this->start->startOfDay()->format('Y-m-d H:i:s');
    }

    public function periodEnd(): string
    {
        return $this->end->endOfDay()->format('Y-m-d H:i:s');
    }

    public function referenceDate(): string
    {
        return $this->reference->toDateString();
    }

    public function referenceEnd(): string
    {
        return $this->reference->endOfDay()->format('Y-m-d H:i:s');
    }

    private static function date(mixed $value, CarbonImmutable $default, string $timezone): CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return $default;
        }

        return CarbonImmutable::createFromFormat('!Y-m-d', $value, $timezone)->startOfDay();
    }
}
