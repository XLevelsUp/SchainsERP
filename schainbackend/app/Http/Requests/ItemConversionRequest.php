<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ItemConversionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => 'required|integer|exists:user_details,user_id',
            'added_at' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.stock_in_id' => 'nullable|integer|exists:stock_details,stock_id',
            'items.*.source_item_id' => 'required|integer|exists:items,item_id',
            'items.*.target_item_id' => 'required|integer|exists:items,item_id|different:items.*.source_item_id',
            'items.*.source_grams' => 'required|numeric|gt:0',
            'items.*.source_touch' => 'required|numeric|between:0,100',
            'items.*.target_touch' => 'required|numeric|between:0,100',
            'items.*.remarks' => 'nullable|string',
            'items.*.item_remarks' => 'nullable|string',
            'items.*.alloys' => 'nullable|array',
            'items.*.alloys.*.alloy_item_id' => 'required|integer|exists:items,item_id',
            'items.*.alloys.*.alloy_percentage' => 'required|numeric|between:0,100',
            'items.*.alloys.*.alloy_grams' => 'required|numeric|gte:0',
            'items.*.added_at' => 'nullable|date',
            'items.*.waste_value' => 'nullable|numeric',
            'items.*.wValue' => 'nullable|numeric',
            'items.*.purity' => 'nullable|numeric',
        ];
    }
}
