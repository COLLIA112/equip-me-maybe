<?php

namespace App\Policies;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\User;

class ReservationPolicy
{
    public function create(User $user): bool
    {
        return $user->isBorrower();
    }

    public function view(User $user, Reservation $reservation): bool
    {
        $reservation->loadMissing('equipment.lab');

        return $user->id === $reservation->user_id
            || $user->managesLab($reservation->equipment->lab_id)
            || $user->headsDepartment($reservation->equipment->lab->department_id);
    }

    /** A student can only cancel their own reservation, and only before it is checked out. */
    public function cancel(User $user, Reservation $reservation): bool
    {
        return $user->id === $reservation->user_id
            && in_array($reservation->status, [ReservationStatus::Pending, ReservationStatus::Approved], true);
    }

    /** Approve, reject, hand over and receive a return: only the lab manager of that lab. */
    public function manage(User $user, Reservation $reservation): bool
    {
        $reservation->loadMissing('equipment');

        return $user->managesLab($reservation->equipment->lab_id);
    }
}
