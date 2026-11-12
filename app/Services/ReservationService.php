<?php

namespace App\Services;

use App\Enums\EquipmentStatus;
use App\Enums\ReservationStatus;
use App\Models\Document;
use App\Models\Equipment;
use App\Models\Reservation;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Holds the business rules so controllers stay thin:
 *  - no overlap, repair gate, compliance gate, and the full checkout lifecycle.
 * Rule violations throw ValidationException, so web requests bounce back with errors
 * and API requests get a 422.
 */
class ReservationService
{
    // ---------- lifecycle ----------

    public function request(User $user, Equipment $equipment, CarbonInterface $start, CarbonInterface $end): Reservation
    {
        return DB::transaction(function () use ($user, $equipment, $start, $end) {
            // Lock the item row so two simultaneous requests cannot both pass the overlap check.
            $equipment = Equipment::whereKey($equipment->id)->lockForUpdate()->firstOrFail();

            $this->assertNotUnderRepair($equipment);
            $this->assertNoConflict($equipment, $start, $end);

            return $equipment->reservations()->create([
                'user_id' => $user->id,
                'starts_at' => $start,
                'ends_at' => $end,
                'status' => ReservationStatus::Pending,
            ]);
        });
    }

    public function approve(Reservation $reservation, User $approver): Reservation
    {
        return DB::transaction(function () use ($reservation, $approver) {
            $this->assertStatus($reservation, ReservationStatus::Pending);

            $equipment = $reservation->equipment;
            $this->assertNotUnderRepair($equipment);
            $this->assertCompliant($reservation);
            $this->assertNoConflict($equipment, $reservation->starts_at, $reservation->ends_at, $reservation->id);

            $reservation->update([
                'status' => ReservationStatus::Approved,
                'approved_by' => $approver->id,
            ]);

            return $reservation;
        });
    }

    public function reject(Reservation $reservation): Reservation
    {
        $this->assertStatus($reservation, ReservationStatus::Pending);
        $reservation->update(['status' => ReservationStatus::Rejected]);

        return $reservation;
    }

    public function cancel(Reservation $reservation): Reservation
    {
        if (! in_array($reservation->status, [ReservationStatus::Pending, ReservationStatus::Approved], true)) {
            throw ValidationException::withMessages(['status' => 'Only pending or approved reservations can be cancelled.']);
        }

        $reservation->update(['status' => ReservationStatus::Cancelled]);

        return $reservation;
    }

    public function handOver(Reservation $reservation): Reservation
    {
        return DB::transaction(function () use ($reservation) {
            $this->assertStatus($reservation, ReservationStatus::Approved);

            $equipment = $reservation->equipment;
            $this->assertNotUnderRepair($equipment);
            $this->assertCompliant($reservation); // checked again so a direct request cannot skip it

            $reservation->update([
                'status' => ReservationStatus::CheckedOut,
                'handed_over_at' => now(),
            ]);
            $equipment->update(['status' => EquipmentStatus::Reserved]);

            return $reservation;
        });
    }

    public function markReturned(Reservation $reservation): Reservation
    {
        return DB::transaction(function () use ($reservation) {
            $this->assertStatus($reservation, ReservationStatus::CheckedOut);

            $reservation->update([
                'status' => ReservationStatus::Returned,
                'returned_at' => now(),
            ]);

            // Back to available, unless it was flagged for repair in the meantime.
            $equipment = $reservation->equipment;
            if ($equipment->status !== EquipmentStatus::UnderRepair) {
                $equipment->update(['status' => EquipmentStatus::Available]);
            }

            return $reservation;
        });
    }

    // ---------- rules ----------

    /** First approved or checked out reservation that overlaps the window, or null. */
    public function findConflict(Equipment $equipment, CarbonInterface $start, CarbonInterface $end, ?int $ignoreId = null): ?Reservation
    {
        return $equipment->reservations()
            ->blocking()
            ->overlapping($start, $end)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->orderBy('starts_at')
            ->first();
    }

    /** Required documents the borrower has not acknowledged yet. */
    public function missingDocuments(Reservation $reservation): Collection
    {
        return Document::query()
            ->where('equipment_id', $reservation->equipment_id)
            ->where('is_required', true)
            ->whereDoesntHave('users', fn ($q) => $q
                ->where('users.id', $reservation->user_id)
                ->whereNotNull('document_user.acknowledged_at'))
            ->get();
    }

    /**
     * Compliance for a whole page of reservations using 2 queries (no N+1).
     * Returns [reservation_id => bool]. The queue screen uses it to enable or disable Approve.
     */
    public function complianceMap(Collection $reservations): array
    {
        $required = Document::whereIn('equipment_id', $reservations->pluck('equipment_id')->unique())
            ->where('is_required', true)
            ->get(['id', 'equipment_id'])
            ->groupBy('equipment_id');

        $acknowledged = DB::table('document_user')
            ->whereIn('user_id', $reservations->pluck('user_id')->unique())
            ->whereNotNull('acknowledged_at')
            ->get(['user_id', 'document_id'])
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->pluck('document_id'));

        return $reservations->mapWithKeys(function (Reservation $r) use ($required, $acknowledged) {
            $needed = ($required[$r->equipment_id] ?? collect())->pluck('id');
            $have = $acknowledged[$r->user_id] ?? collect();

            return [$r->id => $needed->diff($have)->isEmpty()];
        })->all();
    }

    // ---------- assertions ----------

    private function assertNotUnderRepair(Equipment $equipment): void
    {
        if ($equipment->status->blocksReservation()) {
            throw ValidationException::withMessages([
                'equipment' => 'This item is under repair and cannot be reserved until the issue is resolved.',
            ]);
        }
    }

    private function assertNoConflict(Equipment $equipment, CarbonInterface $start, CarbonInterface $end, ?int $ignoreId = null): void
    {
        if ($conflict = $this->findConflict($equipment, $start, $end, $ignoreId)) {
            throw ValidationException::withMessages([
                'starts_at' => sprintf(
                    'This item is already booked from %s to %s.',
                    $conflict->starts_at->format('M j, Y g:i A'),
                    $conflict->ends_at->format('M j, Y g:i A'),
                ),
            ]);
        }
    }

    private function assertCompliant(Reservation $reservation): void
    {
        $missing = $this->missingDocuments($reservation);

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'compliance' => 'The borrower must acknowledge: '.$missing->pluck('title')->implode(', ').'.',
            ]);
        }
    }

    private function assertStatus(Reservation $reservation, ReservationStatus $expected): void
    {
        if ($reservation->status !== $expected) {
            throw ValidationException::withMessages([
                'status' => "Reservation must be {$expected->label()} for this action (currently {$reservation->status->label()}).",
            ]);
        }
    }
}
