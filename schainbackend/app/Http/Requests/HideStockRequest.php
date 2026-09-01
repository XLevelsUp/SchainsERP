<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HideStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'stock_ids' => 'required|array|min:1',
            'stock_ids.*' => 'required|integer|exists:stock_details,stock_id',
        ];
    }
}
