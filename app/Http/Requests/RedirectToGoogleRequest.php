<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RedirectToGoogleRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'client_nonce' => ['required', 'string', 'min:16', 'max:128'],
        ];
    }
}
