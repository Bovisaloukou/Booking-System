<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProviderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->whenLoaded('user', fn () => $this->user->name, $this->user?->name),
            'email' => $this->whenLoaded('user', fn () => $this->user->email, $this->user?->email),
            'bio' => $this->bio,
            'speciality' => $this->speciality,
            'hourly_rate' => $this->hourly_rate,
            'average_rating' => $this->average_rating,
            'services' => ServiceResource::collection($this->whenLoaded('services')),
            'available_slots_count' => $this->whenCounted('availableSlots'),
        ];
    }
}
