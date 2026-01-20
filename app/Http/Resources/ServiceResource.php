<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'duration' => $this->duration,
            'formatted_duration' => $this->formatted_duration,
            'price' => $this->price,
            'formatted_price' => $this->formatted_price,
            'image' => $this->image ? asset('storage/'.$this->image) : null,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'providers_count' => $this->whenCounted('providers'),
        ];
    }
}
