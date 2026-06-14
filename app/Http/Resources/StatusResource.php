<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StatusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'color' => $this->color,
            'type' => $this->type,
            'applies_to' => $this->applies_to,
            'position' => $this->position,
            'is_default' => $this->is_default ?? false,
            'is_closed' => $this->is_closed ?? false,
        ];
    }
}
