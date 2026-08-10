<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreGoldToCashRequest extends FormRequest
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
            // Frontend sends total_gold = pure gold weight (stored in `purity` column)
            'total_gold'                     => 'required|numeric|min:0.001',
            'touch'                          => 'required|numeric|between:0,100',
            // touch-adjusted weight (stored in `total_grams` column)
            'total_grams'                    => 'required|numeric|min:0.001',
            'per_gram_cash'                  => 'required|numeric|min:0.01',
            'total_cash'                     => 'required|numeric|min:0.01',
            'remarks'                        => 'nullable|string|max:5000',
            'added_at'                       => 'nullable|date',
            'is_rate_avg'                    => 'nullable|boolean',
            'retailer_id'                    => 'nullable|integer',

            // Top-level receipt images (all-in-one upload)
            'images'                         => 'nullable|array',
            'images.*'                       => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',

            // Multiple amount sources — head always pays out (amnt_transfer_to_head=1 hardcoded)
            'amount_sources'                 => 'required|array|min:1',
            'amount_sources.*.source'        => 'required|in:CASH_ON_HAND,BANK',
            'amount_sources.*.bank_id'       => 'required_if:amount_sources.*.source,BANK|nullable|integer|exists:bank_details,bank_id',
            'amount_sources.*.amount'        => 'required|numeric|min:0.01',
        ];
    }

    public function messages(): array
    {
        return [
            'head_id.exists'                       => 'The selected head user does not exist.',
            'customer_id.exists'                   => 'The selected customer does not exist.',
            'amount_sources.required'              => 'At least one amount source is required for Gold To Cash.',
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
