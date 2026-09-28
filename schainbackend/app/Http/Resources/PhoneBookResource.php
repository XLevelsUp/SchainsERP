<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PhoneBookResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'user_id'       => $this->user_id,
            'name'          => $this->name,
            'phone_no'      => $this->phone_no,
            'address'       => $this->address,
            'remarks'       => $this->remarks,
            'role_id'       => $this->role_id,
            'profile_image' => $this->profile_image ? url($this->profile_image) : null,
            'is_active'     => (bool) $this->is_active,
            'role'          => $this->whenLoaded('role', fn() => [
                'id'        => $this->role?->id,
                'role_name' => $this->role?->role,
            ]),
        ];
    }
}
