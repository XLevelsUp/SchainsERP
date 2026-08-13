<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashTxnHistoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'txn_id' => $this->txn_id,
            'type' => $this->type,
            'amount' => $this->amount,
            'remarks' => $this->remarks,
            'payment_method' => $this->payment_method,
            'remainder' => $this->remainder,
            'remainder_at' => $this->remainder_at ? \Carbon\Carbon::parse($this->remainder_at)->toDateString() : null,
            'is_hide' => (bool) $this->is_hide,
            'added_at' => $this->created_at ? $this->created_at->toDateString() : null,
            
            // Nested relations with constrained data
            'given_by_user' => $this->whenLoaded('givenByUser', function () {
                return [
                    'user_id' => $this->givenByUser->user_id,
                    'name' => $this->givenByUser->name,
                    'user_name' => $this->givenByUser->user_name,
                ];
            }),
            'given_to_user' => $this->whenLoaded('givenToUser', function () {
                return [
                    'user_id' => $this->givenToUser->user_id,
                    'name' => $this->givenToUser->name,
                    'user_name' => $this->givenToUser->user_name,
                ];
            }),
            'category' => $this->whenLoaded('category', function () {
                return [
                    'category_id' => $this->category->category_id,
                    'category_name' => $this->category->category_name,
                ];
            }),
            'bank' => $this->whenLoaded('bank', function () {
                return [
                    'bank_id' => $this->bank->bank_id,
                    'bank_name' => $this->bank->account_name,
                ];
            }),
        ];
    }
}
