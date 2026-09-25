<?php

namespace App\Http\Requests;

use App\Services\Reports\ReportPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReportRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $prepared = [
            'q' => is_string($this->input('q')) ? trim($this->input('q')) : null,
        ];
        if (! $this->routeIs('relatorios.circulacao*')) {
            $this->merge($prepared);

            return;
        }

        $validDates = collect($this->only(['period_start', 'period_end', 'reference_date']))
            ->filter(fn (mixed $value): bool => is_string($value) && $this->isCalendarDate($value))
            ->all();
        $period = ReportPeriod::from($validDates);

        $this->merge(array_merge($prepared, [
            'period_start' => $this->input('period_start') ?: $period->startDate(),
            'period_end' => $this->input('period_end') ?: $period->endDate(),
            'reference_date' => $this->input('reference_date') ?: $period->referenceDate(),
            'population' => $this->input('population', 'period'),
        ]));
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50])],
        ];

        if ($this->routeIs('relatorios.circulacao*')) {
            $rules += [
                'period_start' => ['required', 'date_format:Y-m-d', 'before_or_equal:period_end'],
                'period_end' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
                'reference_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
                'population' => ['required', Rule::in(['period', 'open', 'overdue'])],
            ];
        }

        return $rules;
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->routeIs('relatorios.circulacao*') || $validator->errors()->isNotEmpty()) {
                return;
            }
            $period = ReportPeriod::from($this->validated());
            if ($period->start->diffInDays($period->end) > 366) {
                $validator->errors()->add('period_start', 'O período deve ter no máximo 366 dias.');
            }
        }];
    }

    public function period(): ReportPeriod
    {
        return ReportPeriod::from($this->validated());
    }

    public function perPage(): int
    {
        return $this->integer('per_page', 25);
    }

    public function search(): ?string
    {
        $search = $this->validated('q');

        return is_string($search) && $search !== '' ? $search : null;
    }

    public function population(): string
    {
        return (string) $this->validated('population', 'period');
    }

    private function isCalendarDate(string $value): bool
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts) !== 1) {
            return false;
        }

        return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
    }
}
