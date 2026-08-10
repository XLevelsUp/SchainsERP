<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCashAutoEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => 'required|string|in:AUTO_ENTRY,EXPENSE,INCOME',
            'head_id' => 'required|integer|exists:user_details,user_id',
            'cash_user_id' => 'required|integer|exists:user_details,user_id',
            
            'transactions' => 'required|array|min:1',
            'transactions.*.amount' => 'required|numeric|min:0.01',
            'transactions.*.category_id' => 'nullable|integer|exists:cash_categories,category_id',
            'transactions.*.remarks' => 'nullable|string|max:5000',
            'transactions.*.added_at' => 'nullable|date',
            
            'transactions.*.images' => 'nullable|array',
            'transactions.*.images.*' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ];
    }
}
