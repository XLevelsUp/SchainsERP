<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCashCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_name' => 'sometimes|required|string|max:255',
            'category_type' => 'nullable|string|in:INCOME,EXPENSE,AUTO_ENTRY',
            'is_active' => 'nullable|boolean',
        ];
    }
}
