<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreSaleGoldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type'                           => 'required|in:SALE_GOLD,SALE_GOLD_CASH,IN_CASH_CONVERTER',
            'head_id'                        => 'required|integer|exists:user_details,user_id',
            'customer_id'                    => 'required|integer|exists:user_details,user_id',
            'total_cash'                     => 'required|numeric|min:0',
            'per_gram_cash'                  => 'required|numeric|min:0',
            'total_grams'                    => 'required|numeric|min:0',
            'touch'                          => 'required|numeric|between:0,100',
            'purity'                         => 'required|numeric|min:0',
            'item_id'                        => 'required|integer|exists:items,item_id',
            'amnt_transfer_to_head'          => 'required|boolean',
            'remarks'                        => 'nullable|string|max:5000',
            'added_at'                       => 'nullable|date',
            'is_rate_avg'                    => 'nullable|boolean',
            'retailer_id'                    => 'nullable|integer|exists:user_details,user_id',
            'bank_entry_date'                => 'nullable|date',
            'stock_in_id'                    => 'nullable|integer|exists:stock_details,stock_id',

            // Top-level receipt images (all-in-one upload)
            'images'                         => 'nullable|array',
            'images.*'                       => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',

            // Optional fields representing frontend inputs
            'taken_total_cash'               => 'nullable|numeric',
            'taken_total_grams'              => 'nullable|numeric',
            'taken_purity'                   => 'nullable|numeric',

            // Multiple amount sources (CASH_ON_HAND and/or BANK rows)
            'amount_sources'                 => 'required_if:amnt_transfer_to_head,true|array',
            'amount_sources.*.source'        => 'required_with:amount_sources|in:CASH_ON_HAND,BANK',
            'amount_sources.*.bank_id'       => 'required_if:amount_sources.*.source,BANK|nullable|integer|exists:bank_details,bank_id',
            'amount_sources.*.amount'        => 'required_with:amount_sources|numeric|min:0.01',
        ];
    }

    public function messages(): array
    {
        return [
            'type.in'                              => 'The type must be SALE_GOLD, SALE_GOLD_CASH, or IN_CASH_CONVERTER.',
            'head_id.exists'                       => 'The selected head user does not exist.',
            'customer_id.exists'                   => 'The selected customer does not exist.',
            'retailer_id.exists'                   => 'The selected retailer does not exist.',
            'item_id.exists'                       => 'The selected item does not exist.',
            'stock_in_id.exists'                   => 'The selected stock in detail does not exist.',
            'amount_sources.required_if'           => 'Amount sources are required when transfer to head is enabled.',
            'amount_sources.*.source.in'           => 'Each source must be CASH_ON_HAND or BANK.',
            'amount_sources.*.bank_id.required_if' => 'Bank is required when source type is BANK.',
            'amount_sources.*.bank_id.exists'      => 'One or more selected bank accounts do not exist.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422)
        );
    }
}
