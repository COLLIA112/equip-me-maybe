<?php

namespace App\Http\Resources;

use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Checkpoint answer for one equipment item. Wrap it like:
 *   new AuthorizationResource($equipment->setRelation('current', $reservationOrNull))
 */
class AuthorizationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Reservation|null $current */
        $current = $this->resource->getRelation('current');

        return [
            'equipment' => [
                'id' => $this->id,
                'code' => $this->qr_code,
                'name' => $this->name,
                'status' => $this->status->value,
            ],
            'authorized' => $current?->isAuthorizedNow() ?? false,
            'borrower' => $current?->user?->name,
            'returns_at' => $current?->ends_at?->toIso8601String(),
        ];
    }
}
