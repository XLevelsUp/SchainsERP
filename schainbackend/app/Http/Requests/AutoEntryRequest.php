<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AutoEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => [
                'required',
                'string',
                Rule::in(['EMPTOEMP', 'EMPTOHEAD', 'ANOTHERHEADTOEMP', 'HEADTOHEAD']),
            ],

            // EMPTOEMP conditional fields
            'from_employee' => [
                Rule::requiredIf($this->type === 'EMPTOEMP'),
                'integer',
                'exists:user_details,user_id',
            ],
            'to_employee' => [
                Rule::requiredIf($this->type === 'EMPTOEMP'),
                'integer',
                'exists:user_details,user_id',
                'different:from_employee',
            ],
            'from_retailer' => ['nullable', 'integer'],
            'to_retailer' => ['nullable', 'integer'],
            'emp_item_id1' => [
                Rule::requiredIf($this->type === 'EMPTOEMP'),
                'integer',
                'exists:items,item_id',
            ],
            'emp_item_id2' => [
                Rule::requiredIf($this->type === 'EMPTOEMP'),
                'integer',
                'exists:items,item_id',
            ],

            // EMPTOHEAD conditional fields
            'from_employee1' => [
                Rule::requiredIf($this->type === 'EMPTOHEAD'),
                'integer',
                'exists:user_details,user_id',
            ],
            'to_head' => [
                Rule::requiredIf($this->type === 'EMPTOHEAD'),
                'integer',
                'exists:user_details,user_id',
                'different:from_employee1',
            ],
            'from_retailer1' => ['nullable', 'integer'],
            'emp_item_id3' => [
                Rule::requiredIf($this->type === 'EMPTOHEAD'),
                'integer',
                'exists:items,item_id',
            ],
            'head_item_id' => [
                Rule::requiredIf($this->type === 'EMPTOHEAD'),
                'integer',
                'exists:items,item_id',
            ],

            // ANOTHERHEADTOEMP conditional fields
            'from_head' => [
                Rule::requiredIf($this->type === 'ANOTHERHEADTOEMP'),
                'integer',
                'exists:user_details,user_id',
            ],
            'to_employee1' => [
                Rule::requiredIf($this->type === 'ANOTHERHEADTOEMP'),
                'integer',
                'exists:user_details,user_id',
                'different:from_head',
            ],
            'to_retailer1' => ['nullable', 'integer'],
            'head_item_id1' => [
                Rule::requiredIf($this->type === 'ANOTHERHEADTOEMP'),
                'integer',
                'exists:items,item_id',
            ],
            'emp_item_id4' => [
                Rule::requiredIf($this->type === 'ANOTHERHEADTOEMP'),
                'integer',
                'exists:items,item_id',
            ],

            // HEADTOHEAD conditional fields
            'from_head1' => [
                Rule::requiredIf($this->type === 'HEADTOHEAD'),
                'integer',
                'exists:user_details,user_id',
            ],
            'to_head1' => [
                Rule::requiredIf($this->type === 'HEADTOHEAD'),
                'integer',
                'exists:user_details,user_id',
                'different:from_head1',
            ],
            'head_item_id2' => [
                Rule::requiredIf($this->type === 'HEADTOHEAD'),
                'integer',
                'exists:items,item_id',
            ],
            'head_item_id3' => [
                Rule::requiredIf($this->type === 'HEADTOHEAD'),
                'integer',
                'exists:items,item_id',
            ],

            // Items list validation
            'items' => ['required', 'array', 'min:1'],
            'items.*.grams' => ['required', 'numeric', 'gt:0'],
            'items.*.touch' => ['required', 'numeric', 'min:1', 'max:999'],
            'items.*.to_touch' => ['required', 'numeric', 'min:1', 'max:999'],
            'items.*.type' => ['required', 'string', Rule::in(['NORMAL', 'GMS', 'FITEM'])],
            'items.*.purity' => ['nullable', 'numeric'],
            'items.*.to_purity' => ['nullable', 'numeric'],
            'items.*.waste_id' => ['nullable', 'integer', 'exists:wastage_details,waste_id'],
            'items.*.waste_total' => ['nullable', 'numeric'],
            'items.*.waste_value' => ['nullable', 'numeric'],
            'items.*.to_waste_id' => ['nullable', 'integer', 'exists:wastage_details,waste_id'],
            'items.*.to_waste_total' => ['nullable', 'numeric'],
            'items.*.to_waste_value' => ['nullable', 'numeric'],
            'items.*.remarks' => ['nullable', 'string', 'max:5000'],
            'items.*.item_remarks' => ['nullable', 'string', 'max:5000'],
            'items.*.added_at' => ['nullable', 'date_format:Y-m-d H:i:s'],

            // GMS specific columns
            'items.*.stone' => ['nullable', 'numeric'],
            'items.*.thread' => ['nullable', 'numeric'],
            'items.*.to_stone' => ['nullable', 'numeric'],
            'items.*.to_thread' => ['nullable', 'numeric'],
            'items.*.gms_mtouch' => ['nullable', 'numeric'],
            'items.*.gms_mthouch_wastage' => ['nullable', 'numeric'],
            'items.*.to_gms_mtouch' => ['nullable', 'numeric'],
            'items.*.to_gms_mthouch_wastage' => ['nullable', 'numeric'],

            // FITEM specific columns
            'items.*.box_id' => ['nullable', 'integer', 'exists:fitem_boxes,box_id'],
            'items.*.mtouch' => ['nullable', 'numeric'],
            'items.*.to_mtouch' => ['nullable', 'numeric'],
        ];
    }
}
