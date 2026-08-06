<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GmsInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'given_by' => 'required|integer|exists:user_details,user_id',
            'given_to' => 'required|integer|exists:user_details,user_id|different:given_by',
            'added_at' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|integer|exists:items,item_id',
            'items.*.grams' => 'required|numeric|gt:0',
            'items.*.stone' => 'nullable|numeric|min:0',
            'items.*.thread' => 'nullable|numeric|min:0',
            'items.*.wastage' => 'nullable|numeric|min:0',
            'items.*.hall_mark' => 'required|numeric|between:0,100',
            'items.*.mtouch' => 'nullable|numeric|min:0',
            'items.*.mtouch_wastage' => 'nullable|numeric|min:0',
            'items.*.remarks' => 'nullable|string|max:5000',
            'items.*.item_remarks' => 'nullable|string|max:5000',
            'items.*.added_at' => 'nullable|date',
            'items.*.waste_value' => 'nullable|numeric',
            'items.*.wValue' => 'nullable|numeric',
            'items.*.purity' => 'nullable|numeric',
        ];
    }
}
