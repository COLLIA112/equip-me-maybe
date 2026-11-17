<?php

namespace App\Services;

use App\Models\Equipment;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Str;

class ReservationNotifier
{
    public static function changed(Reservation $reservation, string $event): void
    {
        $borrower = User::find($reservation->user_id);
        if (! $borrower?->isBorrower()) {
            return;
        }
        $event = Str::afterLast($event, '.');
        $equipment = Equipment::find($reservation->equipment_id);
        $label = match ($event) {
            'requested' => 'Reservation requested',
            'approved' => 'Reservation approved',
            'rejected', 'priority_rejected' => 'Reservation rejected',
            'cancelled' => 'Reservation cancelled',
            'checked_out', 'handed_over' => 'Equipment handed over',
            'returned' => 'Return recorded',
            default => 'Reservation updated',
        };
        $message = $label.' for '.($equipment?->name ?? 'your equipment').'.';
        if (in_array($event, ['rejected', 'priority_rejected'], true) && $reservation->rejection_reason) {
            $message .= ' '.$reservation->rejection_reason;
        }
        NotificationDispatcher::once($borrower, "reservation:{$reservation->id}:{$event}", [
            'kind' => 'reservation', 'title' => $label, 'message' => $message,
            'url' => route('reservations.show', $reservation, false),
            'reservation_id' => $reservation->id, 'equipment_id' => $reservation->equipment_id,
        ]);
    }
}
