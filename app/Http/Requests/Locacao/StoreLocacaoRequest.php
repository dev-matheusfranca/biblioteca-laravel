<?php

namespace App\Http\Requests\Locacao;

use Illuminate\Foundation\Http\FormRequest;

class StoreLocacaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'usuario_id' => ['required', 'exists:users,id'],
            'livro_id' => ['required', 'exists:livros,id'],
            'exemplar_id' => ['nullable', 'exists:exemplares,id'],
            'data_devolucao' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'O campo :attribute é obrigatório.',
            'exists' => 'Selecione um :attribute válido.',
            'date' => 'Informe uma data de devolução válida.',
            'after' => 'A data prevista de devolução deve ser posterior a hoje.',
        ];
    }

    public function attributes(): array
    {
        return [
            'usuario_id' => 'usuário',
            'livro_id' => 'livro',
            'data_devolucao' => 'data prevista de devolução',
        ];
    }
}
