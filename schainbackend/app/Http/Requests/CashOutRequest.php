<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CashOutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'given_to' => 'required|integer|exists:user_details,user_id',
            'amount' => 'required|numeric|gt:0',
            'remarks' => 'nullable|string|max:5000',
        ];
    }
}
