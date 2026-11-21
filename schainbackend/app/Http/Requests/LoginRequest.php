<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Body of POST /api/v1/login and POST /api/v1/admin/login. */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_name' => 'required|string',
            'password' => 'required|string',
        ];
    }
}
