<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ItemChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Item Change is always on the logged-in user's own stock; the
            // service takes the user from the token. Older clients may still
            // send user_id; the controller refuses anyone else's.
            'user_id' => 'nullable|integer',
            'added_at' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.stock_in_id' => 'nullable|integer|exists:stock_details,stock_id',
            'items.*.from_item_id' => 'required|integer|exists:items,item_id',
            'items.*.to_item_id' => 'required|integer|exists:items,item_id|different:items.*.from_item_id',
            'items.*.grams' => 'required|numeric|gt:0',
            'items.*.from_touch' => 'required|numeric|between:0,100',
            'items.*.req_touch' => 'required|numeric|between:0,100',
            'items.*.remarks' => 'nullable|string',
            'items.*.item_remarks' => 'nullable|string',
            'items.*.added_at' => 'nullable|date',
            'items.*.waste_value' => 'nullable|numeric',
            'items.*.wValue' => 'nullable|numeric',
            'items.*.purity' => 'nullable|numeric',
        ];
    }
}
