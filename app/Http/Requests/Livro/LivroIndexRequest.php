<?php

namespace App\Http\Requests\Livro;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LivroIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'categoria' => ['nullable', 'integer', 'exists:categorias,id'],
            'disponibilidade' => ['nullable', Rule::in(['disponivel', 'indisponivel'])],
        ];
    }
}
