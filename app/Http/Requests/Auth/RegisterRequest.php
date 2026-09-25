<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', 'min:8'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'O campo :attribute é obrigatório.',
            'email' => 'Informe um e-mail válido.',
            'unique' => 'Este e-mail já está cadastrado.',
            'confirmed' => 'A confirmação da senha não confere.',
            'min' => 'A senha deve ter pelo menos :min caracteres.',
            'max' => 'O campo :attribute não pode ter mais de :max caracteres.',
        ];
    }

    public function attributes(): array
    {
        return ['nome' => 'nome', 'email' => 'e-mail', 'password' => 'senha'];
    }
}
