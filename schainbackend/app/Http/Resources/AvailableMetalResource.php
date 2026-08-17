<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AvailableMetalResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Determine the party name depending on the entry direction.
        // In legacy Yii2, usually the 'party' is the person who gave it to us (given_by)
        // or the person we gave it to (given_to) depending on stock_type.
        // Since we are querying given_to = user_id, the party who gave it to us is givenBy.
        $partyName = $this->givenBy ? $this->givenBy->name : 'N/A';

        return [
            'id' => $this->stock_id,
            'grams' => (float) $this->grams, // Using grams, or balance depending on legacy logic
            'touch' => (float) $this->touch,
            'purity' => (float) $this->purity,
            'party_name' => $partyName,
            'balance_grams' => (float) $this->balance, // Helpful for the frontend if they need original balance
        ];
    }
}
