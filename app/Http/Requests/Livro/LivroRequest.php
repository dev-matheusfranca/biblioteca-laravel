<?php

namespace App\Http\Requests\Livro;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LivroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'titulo' => ['required', 'string', 'max:255'],
            'autor_id' => ['required', 'exists:autores,id'],
            'categoria_id' => ['required', 'exists:categorias,id'],
            'ano_publicacao' => ['nullable', 'integer', 'min:1000', 'max:'.now()->year],
            'quantidade_total' => ['required', 'integer', 'min:0'],
            'isbn' => ['nullable', 'string', 'max:32'],
            'status' => ['sometimes', 'required', Rule::in(['ativo', 'inativo'])],
        ];
    }

    public function attributes(): array
    {
        return [
            'titulo' => 'título',
            'autor_id' => 'autor',
            'categoria_id' => 'categoria',
            'ano_publicacao' => 'ano de publicação',
            'quantidade_total' => 'quantidade total',
            'status' => 'status',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'O campo :attribute é obrigatório.',
            'string' => 'O campo :attribute deve ser um texto.',
            'integer' => 'O campo :attribute deve ser um número inteiro.',
            'exists' => 'Selecione um :attribute válido.',
            'min' => 'O campo :attribute deve ser no mínimo :min.',
            'max' => 'O campo :attribute não pode ser maior que :max.',
            'in' => 'Selecione um status válido.',
        ];
    }
}
