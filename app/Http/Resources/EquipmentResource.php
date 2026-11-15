<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EquipmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->qr_code,
            'name' => $this->name,
            'serial_number' => $this->serial_number,
            'status' => $this->status->value,
            'status_label' => $this->status_label,
            'photo_url' => $this->photo_url,
            'lab' => $this->whenLoaded('lab', fn () => [
                'id' => $this->lab->id,
                'name' => $this->lab->name,
            ]),
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->pluck('name')),
        ];
    }
}
