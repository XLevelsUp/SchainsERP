<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockCashHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $headId = $request->query('head_id');

        // Logic to show the "Other User". If the current logged in user (or passed head_id) is given_by, 
        // the other user is given_to, and vice versa.
        // For simplicity, we can return the name directly based on who is who.
        $userName = '';
        if ($headId) {
            if ($this->given_by == $headId && $this->relationLoaded('givenTo')) {
                $userName = $this->givenTo->name ?? '';
            } else if ($this->relationLoaded('givenBy')) {
                $userName = $this->givenBy->name ?? '';
            }
        } else {
            // Default fallback
            $userName = $this->relationLoaded('givenBy') ? ($this->givenBy->name ?? '') : '';
        }

        return [
            'id' => $this->stock_id,
            'item' => $this->whenLoaded('item', function () {
                return $this->item->item_name;
            }),
            'stock_type' => $this->stock_type,
            'grams' => $this->grams,
            'no_of_pcs' => $this->no_of_pcs ?? 0,
            'touch' => $this->touch,
            'wastage' => $this->waste_total ?? ($this->waste_value ?? 0),
            'purity' => $this->purity,
            'user' => $userName,
            'remarks' => $this->remarks,
        ];
    }
}
