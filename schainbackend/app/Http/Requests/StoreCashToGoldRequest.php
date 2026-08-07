<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreCashToGoldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'head_id'                        => 'required|integer|exists:user_details,user_id',
            'customer_id'                    => 'required|integer|exists:user_details,user_id',
            'total_cash'                     => 'required|numeric|min:0.01',
            'per_gram_cash'                  => 'required|numeric|min:0.01',
            'total_grams'                    => 'required|numeric|min:0.001',
            'touch'                          => 'required|numeric|between:0,100',
            'purity'                         => 'required|numeric|min:0.001',
            'item_id'                        => 'required|integer|exists:items,item_id',
            'amnt_transfer_to_head'          => 'required|boolean',
            'remarks'                        => 'nullable|string|max:5000',
            'added_at'                       => 'nullable|date',
            'is_rate_avg'                    => 'nullable|boolean',
            'retailer_id'                    => 'nullable|integer',

            // Multiple amount sources (CASH_ON_HAND and/or BANK rows)
            'amount_sources'                 => 'required_if:amnt_transfer_to_head,true|array|min:1',
            'amount_sources.*.source'        => 'required_with:amount_sources|in:CASH_ON_HAND,BANK',
            'amount_sources.*.bank_id'       => 'required_if:amount_sources.*.source,BANK|nullable|integer|exists:bank_details,bank_id',
            'amount_sources.*.amount'        => 'required_with:amount_sources|numeric|min:0.01',
        ];
    }

    public function messages(): array
    {
        return [
            'head_id.exists'                       => 'The selected head user does not exist.',
            'customer_id.exists'                   => 'The selected customer does not exist.',
            'item_id.exists'                       => 'The selected item does not exist.',
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
