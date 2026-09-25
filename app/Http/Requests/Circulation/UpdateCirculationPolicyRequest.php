<?php

namespace App\Http\Requests\Circulation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCirculationPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'loan_days' => ['required', 'integer', 'between:1,365'],
            'max_open_loans' => ['required', 'integer', 'between:1,100'],
            'max_renewals' => ['required', 'integer', 'between:0,20'],
            'renewal_days' => ['required', 'integer', 'between:1,365'],
            'pickup_hours' => ['required', 'integer', 'between:1,720'],
            'blocks_overdue' => ['required', 'boolean'],
            'timezone' => ['required', Rule::in(['America/Sao_Paulo'])],
            'reason' => ['required', 'string', 'max:1000'],
            'expected_version' => ['required', 'integer', 'min:1'],
        ];
    }
}
