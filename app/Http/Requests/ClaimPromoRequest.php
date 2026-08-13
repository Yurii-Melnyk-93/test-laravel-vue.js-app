<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClaimPromoRequest extends FormRequest
{
    /**
     * The player is taken from the token, never from the payload, so `code`
     * is the only thing this endpoint accepts.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'regex:/^[A-Za-z0-9]{6,12}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Введіть промокод.',
            'code.regex' => 'Промокод має містити 6–12 латинських літер або цифр.',
        ];
    }
}
