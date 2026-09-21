<?php

namespace App\Http\Requests\Locacao;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LocacaoIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['ativa', 'devolvida', 'atrasada'])],
        ];
    }
}
