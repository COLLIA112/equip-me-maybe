<?php

namespace App\Services;

use App\Enums\EquipmentStatus;
use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Models\Document;
use App\Models\Equipment;
use App\Models\Lab;
use App\Models\LabClosure;
use App\Models\MaintenanceLog;
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
    public const FACULTY_PRIORITY_REASON = 'An overlapping faculty class session received priority over this pending student request.';

    // ---------- lifecycle ----------

    public function request(
        User $user,
        Equipment $equipment,
        CarbonInterface $start,
        CarbonInterface $end,
        bool $isClassSession = false,
    ): Reservation {
        if ($start->lt(now()) || $start->gt(now()->addDays(90))) {
            throw ValidationException::withMessages(['starts_at' => 'Choose a start time within the next 90 days.']);
        }
        if ($end->lte($start) || $end->gt($start->copy()->addHours(24))) {
            throw ValidationException::withMessages(['ends_at' => 'Reservations may last up to 24 hours.']);
        }

        return DB::transaction(function () use ($user, $equipment, $start, $end, $isClassSession) {
            // Lock the item row so two simultaneous requests cannot both pass the overlap check.
            $equipment = Equipment::whereKey($equipment->id)->lockForUpdate()->firstOrFail();

            $this->assertNotUnderRepair($equipment);
            $this->assertLabOpen($equipment, $start, $end);
            $this->assertNoConflict($equipment, $start, $end);

            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($user->isBorrower(), 403);
            $pending = $user->reservations()->where('status', ReservationStatus::Pending)
                ->where('ends_at', '>', now())->lockForUpdate()->pluck('id');
            if ($pending->count() >= 5) {
                throw ValidationException::withMessages(['starts_at' => 'Resolve a pending request before making another.']);
            }

            $isClassSession = $isClassSession && $user->hasRole(Role::Faculty);
            if ($isClassSession) {
                $displaced = $equipment->reservations()
                    ->overlapping($start, $end)->where('status', ReservationStatus::Pending)
                    ->whereHas('user', fn ($query) => $query->where('role', Role::Student))->get();
                foreach ($displaced as $pending) {
                    $pending->update([
                        'status' => ReservationStatus::Rejected,
                        'rejection_reason' => self::FACULTY_PRIORITY_REASON,
                        'rejected_at' => now(),
                    ]);
                    $this->recordChange('rejected', null, $equipment, $pending, ['reason' => self::FACULTY_PRIORITY_REASON]);
                }
            }

            $reservation = $equipment->reservations()->create([
                'user_id' => $user->id,
                'starts_at' => $start,
                'ends_at' => $end,
                'status' => ReservationStatus::Pending,
                'is_class_session' => $isClassSession,
            ]);
            $this->recordChange('requested', $user, $equipment, $reservation);

            return $reservation;
        });
    }

    public function approve(Reservation $reservation, User $approver): Reservation
    {
        return DB::transaction(function () use ($reservation, $approver) {
            $equipment = Equipment::whereKey($reservation->equipment_id)->lockForUpdate()->firstOrFail();

            // Route binding may have loaded the reservation before another approval committed.
            $reservation->refresh();
            $this->assertStatus($reservation, ReservationStatus::Pending);
            if ($reservation->starts_at->lt(now())) {
                throw ValidationException::withMessages(['starts_at' => 'This reservation has already started.']);
            }

            $this->assertNotUnderRepair($equipment);
            $this->assertCompliant($reservation);
            $this->assertLabOpen($equipment, $reservation->starts_at, $reservation->ends_at);
            $this->assertNoConflict($equipment, $reservation->starts_at, $reservation->ends_at, $reservation->id);

            $reservation->update([
                'status' => ReservationStatus::Approved,
                'approved_by' => $approver->id,
                'approved_at' => now(),
            ]);

            $this->recordChange('approved', $approver, $equipment, $reservation);

            return $reservation;
        });
    }

    public function reject(Reservation $reservation, ?string $reason = null, ?User $actor = null): Reservation
    {
        return DB::transaction(function () use ($reservation, $reason, $actor) {
            $equipment = Equipment::whereKey($reservation->equipment_id)->lockForUpdate()->firstOrFail();
            $reservation->refresh();
            $this->assertStatus($reservation, ReservationStatus::Pending);
            $reservation->update([
                'status' => ReservationStatus::Rejected,
                'rejection_reason' => filled($reason) ? trim($reason) : null,
                'rejected_by' => $actor?->id,
                'rejected_at' => now(),
            ]);

            $this->recordChange('rejected', $actor, $equipment, $reservation);

            return $reservation;
        });
    }

    public function cancel(Reservation $reservation, ?User $actor = null): Reservation
    {
        return DB::transaction(function () use ($reservation, $actor) {
            $equipment = Equipment::whereKey($reservation->equipment_id)->lockForUpdate()->firstOrFail();
            $reservation->refresh();

            if (! in_array($reservation->status, [ReservationStatus::Pending, ReservationStatus::Approved], true)) {
                throw ValidationException::withMessages(['status' => 'Only pending or approved reservations can be cancelled.']);
            }

            $reservation->update([
                'status' => ReservationStatus::Cancelled,
                'cancelled_by' => $actor?->id,
                'cancelled_at' => now(),
            ]);

            $this->recordChange('cancelled', $actor, $equipment, $reservation);

            return $reservation;
        });
    }

    public function expire(Reservation $reservation): bool
    {
        return DB::transaction(function () use ($reservation) {
            $equipment = Equipment::whereKey($reservation->equipment_id)->lockForUpdate()->firstOrFail();
            $reservation->refresh();
            if (! in_array($reservation->status, [ReservationStatus::Pending, ReservationStatus::Approved], true)
                || $reservation->ends_at->gt(now())) {
                return false;
            }

            $reservation->update(['status' => ReservationStatus::Cancelled, 'cancelled_at' => now()]);
            $this->recordChange('cancelled', null, $equipment, $reservation, ['reason' => 'expired']);

            return true;
        }, 3);
    }

    public function handOver(Reservation $reservation, ?User $actor = null): Reservation
    {
        return DB::transaction(function () use ($reservation, $actor) {
            $equipment = Equipment::whereKey($reservation->equipment_id)->lockForUpdate()->firstOrFail();
            $reservation->refresh();
            $this->assertStatus($reservation, ReservationStatus::Approved);

            $this->assertNotUnderRepair($equipment);
            $this->assertCompliant($reservation); // checked again so a direct request cannot skip it

            $now = now();
            if ($now->lt($reservation->starts_at) || $now->gte($reservation->ends_at)) {
                throw ValidationException::withMessages(['starts_at' => 'Handover is only possible during the reservation.']);
            }
            if ($equipment->reservations()->checkedOut()->exists()) {
                throw ValidationException::withMessages(['equipment' => 'Receive the current checkout before handing over this item.']);
            }

            $reservation->update([
                'status' => ReservationStatus::CheckedOut,
                'handed_over_at' => now(),
                'handed_over_by' => $actor?->id,
            ]);
            $equipment->update(['status' => EquipmentStatus::Reserved]);

            $this->recordChange('checked_out', $actor, $equipment, $reservation);

            return $reservation;
        });
    }

    public function markReturned(Reservation $reservation, ?User $actor = null, string $condition = 'good', ?string $notes = null): Reservation
    {
        return DB::transaction(function () use ($reservation, $actor, $condition, $notes) {
            $equipment = Equipment::whereKey($reservation->equipment_id)->lockForUpdate()->firstOrFail();
            $reservation->refresh();
            $this->assertStatus($reservation, ReservationStatus::CheckedOut);
            if (! in_array($condition, ['good', 'damaged', 'missing_parts'], true)
                || ($condition !== 'good' && (! $actor || ! filled($notes)))) {
                throw ValidationException::withMessages(['return_notes' => 'Record the damage or missing parts before returning this item.']);
            }

            $reservation->update([
                'status' => ReservationStatus::Returned,
                'returned_at' => now(),
                'returned_by' => $actor?->id,
                'return_condition' => $condition,
                'return_notes' => filled($notes) ? trim($notes) : null,
            ]);

            if ($condition !== 'good') {
                MaintenanceLog::create([
                    'equipment_id' => $equipment->id, 'reservation_id' => $reservation->id,
                    'reported_by' => $actor->id,
                    'issue' => ($condition === 'damaged' ? 'Damage on return: ' : 'Missing parts on return: ').trim($notes),
                ]);
                $equipment->update(['status' => EquipmentStatus::UnderRepair]);
            }

            // Back to available, unless it was flagged for repair in the meantime.
            if ($equipment->status !== EquipmentStatus::UnderRepair) {
                $equipment->update([
                    'status' => $equipment->reservations()->checkedOut()->exists()
                        ? EquipmentStatus::Reserved
                        : EquipmentStatus::Available,
                ]);
            }

            $this->recordChange('returned', $actor, $equipment, $reservation, ['condition' => $condition]);

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
        return array_map(fn (array $titles) => $titles === [], $this->missingDocumentMap($reservations));
    }

    /** Missing document titles for a whole page, using two queries rather than one per row. */
    public function missingDocumentMap(Collection $reservations): array
    {
        $required = Document::whereIn('equipment_id', $reservations->pluck('equipment_id')->unique())
            ->where('is_required', true)
            ->orderBy('title')
            ->get(['id', 'equipment_id', 'title'])
            ->groupBy('equipment_id');

        $acknowledged = DB::table('document_user')
            ->whereIn('user_id', $reservations->pluck('user_id')->unique())
            ->whereNotNull('acknowledged_at')
            ->get(['user_id', 'document_id'])
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->pluck('document_id'));

        return $reservations->mapWithKeys(function (Reservation $reservation) use ($required, $acknowledged) {
            $have = $acknowledged[$reservation->user_id] ?? collect();
            $missing = ($required[$reservation->equipment_id] ?? collect())
                ->reject(fn (Document $document) => $have->contains($document->id))
                ->pluck('title')->all();

            return [$reservation->id => $missing];
        })->all();
    }

    // ---------- assertions ----------

    private function recordChange(string $event, ?User $actor, Equipment $equipment, Reservation $reservation, array $details = []): void
    {
        app(ActivityRecorder::class)->record($event, $actor, $equipment, $reservation, $details);
        app(ReservationNotifier::class)->changed($reservation, $event);
        if (in_array($event, ['approved', 'checked_out', 'returned', 'cancelled'], true)) {
            AvailabilityNotifier::notify($equipment);
        }
    }

    private function assertLabOpen(Equipment $equipment, CarbonInterface $start, CarbonInterface $end): void
    {
        // Closure writes take the same lab lock. Use a current read after waiting on it.
        Lab::whereKey($equipment->lab_id)->lockForUpdate()->firstOrFail();
        $closure = LabClosure::query()->where('lab_id', $equipment->lab_id)
            ->where('starts_at', '<', $end)->where('ends_at', '>', $start)->orderBy('starts_at')->lockForUpdate()->first();
        if ($closure) {
            throw ValidationException::withMessages(['starts_at' => 'This lab is unavailable from '.$closure->starts_at->format('M j, Y g:i A').' to '.$closure->ends_at->format('M j, Y g:i A').': '.$closure->reason]);
        }
    }

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
