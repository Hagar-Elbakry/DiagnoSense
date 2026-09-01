<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ExchangeSocialCodeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'string'],
            'client_nonce' => ['required', 'string', 'min:16', 'max:128'],
        ];
    }
}
