<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'handed_over_at' => $this->handed_over_at?->toIso8601String(),
            'returned_at' => $this->returned_at?->toIso8601String(),
            'borrower' => $this->whenLoaded('user', fn () => $this->user->name),
            'equipment' => new EquipmentResource($this->whenLoaded('equipment')),
        ];
    }
}
