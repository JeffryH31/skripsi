<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'initials' => $this->initials,
            'avatar_color' => $this->avatar_color,
            'profile_photo_url' => $this->profile_photo_url,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
