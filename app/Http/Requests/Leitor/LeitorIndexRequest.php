<?php

namespace App\Http\Requests\Leitor;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeitorIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isStaff() ?? false;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['ativos', 'inativos'])],
        ];
    }
}
