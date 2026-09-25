<?php

namespace App\Http\Requests\Token;

use App\Support\PersonalTokenAbilities;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePersonalAccessTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'current_password' => ['required', 'current_password'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['required', 'string', 'distinct', Rule::in(array_keys(PersonalTokenAbilities::ALL))],
        ];
    }
}
