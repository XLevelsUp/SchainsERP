<?php

namespace App\Http\Resources;

use App\Models\UserDetail;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SystemLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Extract customer / party information from metadata or payload
        $customerName = $this->metadata['customer_name'] 
            ?? $this->metadata['recipient_name'] 
            ?? $this->metadata['sender_name'] 
            ?? $this->metadata['given_to_name'] 
            ?? $this->payload['customer_name'] 
            ?? null;

        $customerId = $this->metadata['customer_id'] 
            ?? $this->payload['customer_id'] 
            ?? $this->payload['recipient_id'] 
            ?? $this->payload['given_to'] 
            ?? null;

        return [
            'id'              => $this->id,
            'module'          => $this->module,
            'action'          => $this->action,
            'sub_category'    => $this->action, // Subcategory alias for frontend clarity
            'user_id'         => $this->user_id,
            'user'            => $this->whenLoaded('user', function () {
                return $this->user ? [
                    'user_id'   => $this->user->user_id,
                    'id'        => $this->user->user_id,
                    'name'      => $this->user->name,
                    'user_name' => $this->user->user_name,
                    'role_id'   => $this->user->role_id,
                ] : null;
            }),
            'customer_id'     => $customerId ? (int) $customerId : null,
            'customer_name'   => $customerName,
            'customer'        => $customerId ? [
                'customer_id'   => (int) $customerId,
                'id'            => (int) $customerId,
                'name'          => $customerName,
                'customer_name' => $customerName,
            ] : null,
            'reference_id'    => $this->reference_id,
            'reference_table' => $this->reference_table,
            'description'     => $this->description,
            'payload'         => $this->payload,
            'metadata'        => $this->metadata,
            'ip_address'      => $this->ip_address,
            'user_agent'      => $this->user_agent,
            'created_at'      => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'created_at_human'=> $this->created_at ? $this->created_at->diffForHumans() : null,
        ];
    }
}
