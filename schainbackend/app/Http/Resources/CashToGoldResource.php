<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CashToGoldResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'cash_to_gold_id'       => $this->cash_to_gold_id,
            'type'                  => $this->type,
            'head_id'               => $this->head_id,
            'customer_id'           => $this->customer_id,
            'total_cash'            => (float) $this->total_cash,
            'per_gram_cash'         => (float) $this->per_gram_cash,
            'total_grams'           => (float) $this->total_grams,
            'touch'                 => (float) $this->touch,
            'purity'                => (float) $this->purity,
            'item_id'               => $this->item_id,
            'stock_id'              => $this->stock_id,
            'amnt_transfer_to_head' => (bool) $this->amnt_transfer_to_head,
            'ob_grams'              => (float) $this->ob_grams,
            'ob_purity'             => (float) $this->ob_purity,
            'remarks'               => $this->remarks,
            'is_rate_avg'           => (bool) $this->is_rate_avg,
            'retailer_id'           => $this->retailer_id,
            'added_at'              => $this->added_at?->toDateTimeString(),

            // Relations (loaded eagerly)
            'head' => $this->whenLoaded('head', fn() => [
                'user_id'           => $this->head->user_id,
                'name'              => $this->head->name,
                'cash_balance'      => (float) $this->head->cash_balance,
                'rtgs_balance'      => (float) $this->head->rtgs_balance,
                'grams_grand_total' => (float) $this->head->grams_grand_total,
                'purity_grand_total'=> (float) $this->head->purity_grand_total,
            ]),

            'customer' => $this->whenLoaded('customer', fn() => [
                'user_id'           => $this->customer->user_id,
                'name'              => $this->customer->name,
                'cash_balance'      => (float) $this->customer->cash_balance,
                'rtgs_balance'      => (float) $this->customer->rtgs_balance,
                'grams_grand_total' => (float) $this->customer->grams_grand_total,
                'purity_grand_total'=> (float) $this->customer->purity_grand_total,
            ]),

            'item' => $this->whenLoaded('item', fn() => [
                'item_id'   => $this->item->item_id,
                'item_name' => $this->item->item_name,
            ]),

            // All payment sources (CASH_ON_HAND + BANK rows)
            'amount_sources' => $this->whenLoaded('amountSources', fn() =>
                $this->amountSources->map(fn($src) => [
                    'id'           => $src->id,
                    'source'       => $src->souce_type,
                    'bank_id'      => $src->bank_id,
                    'amount'       => (float) $src->amount,
                    'cash_txn_id'  => $src->cash_txn_id,
                ])
            ),

            'cash_txn_ids' => $this->whenLoaded('cashTxnDetails', fn() =>
                $this->cashTxnDetails->pluck('txn_id')
            ),
        ];
    }
}
