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
            'profile_image' => $this->profile_image,
            'is_active'     => $this->is_active,
            'role'          => $this->whenLoaded('role', fn() => [
                'id'        => $this->role?->id,
                'role_name' => $this->role?->role_name,
            ]),
        ];
    }
}
