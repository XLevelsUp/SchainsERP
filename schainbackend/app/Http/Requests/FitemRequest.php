<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FitemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => 'required|string|in:IN,OUT',
            'given_by' => 'required|integer|exists:user_details,user_id',
            'given_to' => 'required|integer|exists:user_details,user_id|different:given_by',
            'added_at' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|integer|exists:items,item_id',
            'items.*.grams' => 'required|numeric|gt:0',
            'items.*.touch' => 'required|numeric|between:0,100',
            'items.*.mtouch' => 'nullable|numeric|between:0,100',
            'items.*.wastage' => 'nullable|numeric|min:0',
            'items.*.box_id' => 'nullable|integer', 
            'items.*.item_gross_weight' => 'nullable|numeric|min:0',
            'items.*.item_added_gross_grams' => 'nullable|numeric|min:0',
            'items.*.item_no_of_pcs' => 'nullable|integer|min:0',
            'items.*.item_remarks' => 'nullable|string|max:5000',
            'items.*.remarks' => 'nullable|string|max:5000',
            'items.*.added_at' => 'nullable|date',
        ];
    }
}
