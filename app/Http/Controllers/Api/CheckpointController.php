<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuthorizationResource;
use App\Http\Resources\ReservationResource;
use App\Models\Equipment;
use App\Models\Reservation;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CheckpointController extends Controller
{
    /** GET /api/equipment/{code}/authorization : who holds it, until when, and is it authorized right now. */
    public function authorization(Equipment $equipment): AuthorizationResource
    {
        $equipment->setRelation('current', $equipment->currentCheckout());

        return new AuthorizationResource($equipment);
    }

    /** GET /api/reservations/active : everything currently checked out. */
    public function active(): AnonymousResourceCollection
    {
        $reservations = Reservation::query()
            ->checkedOut()
            ->with(['equipment', 'user'])
            ->orderBy('ends_at')
            ->get();

        return ReservationResource::collection($reservations);
    }
}
