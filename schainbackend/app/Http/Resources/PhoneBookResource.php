<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/** A phone book contact (App\Models\PhoneBook), for the console and the app. */
class PhoneBookResource extends JsonResource
{
    public function toArray($request): array
    {
        $picture = $this->relationLoaded('user') ? $this->user?->profile_image : null;

        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'phone_no'          => $this->phone_no,
            'role_id'           => $this->role_id,
            'is_active'         => (bool) $this->is_active,
            // The user the contact was copied from, if any.
            'user_id'           => $this->user_id,
            // That user's profile picture; null for other contacts.
            'profile_image_url' => $picture ? asset('storage/' . $picture) : null,
            'role'              => $this->whenLoaded('role', fn () => [
                'id'        => $this->role?->id,
                'role_name' => $this->role?->role,
            ]),
            'created_at'        => $this->created_at?->toIso8601String(),
            'updated_at'        => $this->updated_at?->toIso8601String(),
        ];
    }
}
