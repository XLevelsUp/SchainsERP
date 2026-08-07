<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCashTxnDetailRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation by mapping legacy field names.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'sender_id' => $this->input('sender_id') ?? $this->input('given_by'),
            'recipient_id' => $this->input('recipient_id') ?? $this->input('given_to'),
            'payment_method' => $this->input('payment_method') ?? $this->input('souce_type'),
            'bank_account_id' => $this->input('bank_account_id') ?? $this->input('bank_id'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'sender_id' => 'required|integer|exists:user_details,user_id',
            'recipient_id' => 'required|integer|exists:user_details,user_id|different:sender_id',
            'category_id' => 'nullable|integer',
            'amount' => [
                'required',
                'numeric',
                'min:0.01',
                'regex:/^\d+(\.\d{1,2})?$/', // max 2 decimal places for cash
            ],
            'payment_method' => 'required|string|in:CASH_ON_HAND,BANK',
            'bank_account_id' => 'nullable|integer|required_if:payment_method,BANK|exists:bank_details,bank_id',
            'remarks' => 'nullable|string|max:5000',
            'images' => 'nullable|array',
            'images.*' => 'string|max:255', // paths of pre-uploaded receipt attachments
        ];
    }

    /**
     * Custom validation messages.
     */
    public function messages(): array
    {
        return [
            'amount.regex' => 'The cash transaction amount must be a numeric value with at most two decimal places.',
            'bank_account_id.required_if' => 'The bank account field is required when payment method is BANK.',
            'recipient_id.different' => 'The recipient cannot be the same user as the sender.',
        ];
    }
}
