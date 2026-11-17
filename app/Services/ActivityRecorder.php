<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Equipment;
use App\Models\Reservation;
use App\Models\User;

class ActivityRecorder
{
    public static function record(string $event, ?User $actor, ?Equipment $equipment = null, ?Reservation $reservation = null, array $details = []): ActivityLog
    {
        if ($equipment) {
            $equipment->loadMissing('lab');
            $details = array_merge($details, [
                'equipment_name' => $equipment->name,
                'lab_id' => $equipment->lab_id,
                'department_id' => $equipment->lab->department_id,
            ]);
        }

        return ActivityLog::create([
            'actor_id' => $actor?->id,
            'equipment_id' => $equipment?->id ?? $reservation?->equipment_id,
            'reservation_id' => $reservation?->id,
            'event' => $event,
            'details' => $details,
        ]);
    }
}
