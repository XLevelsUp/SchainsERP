<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class NumericWasteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'given_by' => 'nullable|integer|exists:user_details,user_id',
            'given_to' => 'required|integer|exists:user_details,user_id',
            'added_at' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|integer|exists:items,item_id',
            'items.*.grams' => 'required|numeric|gt:0',
            'items.*.touch' => 'required|numeric|between:0,100',
            'items.*.no_of_pcs' => 'required|numeric|gt:0',
            'items.*.amount_pcs' => 'nullable|numeric|min:0',
            'items.*.waste_id' => 'nullable|integer|exists:wastage_details,waste_id',
            'items.*.waste_total' => 'nullable|numeric|min:0',
            'items.*.waste_value' => 'nullable|numeric|min:0',
            'items.*.amount' => 'nullable|numeric|min:0',
            'items.*.remarks' => 'nullable|string',
            'items.*.item_remarks' => 'nullable|string',
            'items.*.added_at' => 'nullable|date',
            'items.*.wValue' => 'nullable|numeric',
            'items.*.purity' => 'nullable|numeric',
        ];
    }
}
