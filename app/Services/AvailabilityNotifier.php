<?php

namespace App\Services;

use App\Enums\EquipmentStatus;
use App\Models\Equipment;
use App\Models\LabClosure;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AvailabilityNotifier
{
    public static function isAvailableNow(Equipment $equipment): bool
    {
        $now = now();

        return $equipment->status !== EquipmentStatus::UnderRepair
            && ! $equipment->maintenanceLogs()->whereNull('resolved_at')->exists()
            && ! $equipment->reservations()->checkedOut()->exists()
            && ! $equipment->reservations()->blocking()->where('starts_at', '<=', $now)->where('ends_at', '>', $now)->exists()
            && ! LabClosure::where('lab_id', $equipment->lab_id)->where('starts_at', '<=', $now)->where('ends_at', '>', $now)->exists();
    }

    /** Re-arm when blocked, then send once when the item becomes free again. */
    public static function notify(Equipment $equipment): void
    {
        DB::transaction(function () use ($equipment) {
            $equipment = Equipment::whereKey($equipment->id)->lockForUpdate()->firstOrFail();
            if (! self::isAvailableNow($equipment)) {
                DB::table('wishlists')->where('equipment_id', $equipment->id)->whereNotNull('notified_at')
                    ->update(['notified_at' => null, 'availability_cycle' => DB::raw('availability_cycle + 1'), 'updated_at' => now()]);

                return;
            }
            DB::table('wishlists')->where('equipment_id', $equipment->id)->whereNull('notified_at')
                ->chunkById(100, function ($wishlists) use ($equipment) {
                    $borrowers = User::whereKey($wishlists->pluck('user_id')->all())->get()->keyBy('id');
                    foreach ($wishlists as $wishlist) {
                        $borrower = $borrowers->get($wishlist->user_id);
                        if ($borrower?->isBorrower()) {
                            NotificationDispatcher::once($borrower, "availability:{$wishlist->id}:{$wishlist->availability_cycle}", [
                                'kind' => 'availability', 'title' => 'Wishlist item available',
                                'message' => $equipment->name.' is free now. Availability is checked again when you reserve.',
                                'url' => route('equipment.show', $equipment, false), 'equipment_id' => $equipment->id,
                            ]);
                        }
                    }
                    DB::table('wishlists')->whereIn('id', $wishlists->pluck('id'))
                        ->update(['notified_at' => now(), 'updated_at' => now()]);
                });
        }, 3);
    }
}
