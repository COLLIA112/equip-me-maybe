<?php

namespace App\Console\Commands;

use App\Models\Equipment;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Services\AvailabilityNotifier;
use App\Services\NotificationDispatcher;
use App\Services\ReservationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NotifyBorrowers extends Command
{
    protected $signature = 'qol:notify';

    protected $description = 'Create in-app due/overdue reminders and wishlist availability notifications without duplicates';

    public function handle(ReservationService $reservations): int
    {
        $sent = 0;
        $now = now();
        Reservation::whereIn('status', [ReservationStatus::Pending, ReservationStatus::Approved])
            ->where('ends_at', '<=', $now)->eachById(function (Reservation $reservation) use ($reservations) {
                $reservations->expire($reservation);
            });
        Reservation::checkedOut()->where('ends_at', '<=', $now->copy()->addHours(2))
            ->with(['equipment', 'user'])->chunkById(100, function ($reservations) use ($now, &$sent) {
                foreach ($reservations as $reservation) {
                    if (! $reservation->user->isBorrower()) {
                        continue;
                    }
                    $overdue = $reservation->ends_at->lt($now);
                    $kind = $overdue ? 'overdue' : 'due';
                    $minutes = max(0, (int) ceil($now->diffInMinutes($reservation->ends_at, false)));
                    $timing = $overdue ? 'is overdue' : ($minutes === 0 ? 'is due now' : 'is due back in '.($minutes >= 60 ? (int) ceil($minutes / 60).' hours' : $minutes.' minutes'));
                    $sent += NotificationDispatcher::once($reservation->user, "return:{$reservation->id}:{$kind}:{$reservation->ends_at->timestamp}", [
                        'kind' => $kind, 'title' => $overdue ? 'Return overdue' : 'Return reminder',
                        'message' => $reservation->equipment->name.' '.$timing.'.',
                        'url' => route('reservations.show', $reservation, false),
                        'reservation_id' => $reservation->id, 'equipment_id' => $reservation->equipment_id,
                    ]) ? 1 : 0;
                }
            });
        Equipment::whereIn('id', DB::table('wishlists')->select('equipment_id'))->chunkById(100, function ($equipment) {
            foreach ($equipment as $item) {
                AvailabilityNotifier::notify($item);
            }
        });
        $this->info("Created {$sent} return reminder(s); checked wishlist availability.");

        return self::SUCCESS;
    }
}
