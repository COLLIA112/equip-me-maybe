<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Reservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'equipment_id', 'user_id', 'approved_by', 'starts_at', 'ends_at',
        'status', 'handed_over_at', 'returned_at', 'is_class_session',
        'rejection_reason', 'approved_at', 'handed_over_by', 'returned_by',
        'rejected_by', 'rejected_at', 'cancelled_by', 'cancelled_at',
        'return_condition', 'return_notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReservationStatus::class,
            'is_class_session' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'handed_over_at' => 'datetime',
            'returned_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by')->withTrashed();
    }

    public function handoverManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handed_over_by')->withTrashed();
    }

    public function returnManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by')->withTrashed();
    }

    public function rejector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by')->withTrashed();
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by')->withTrashed();
    }

    public function canReserveAgain(User $user): bool
    {
        return $user->isBorrower() && $this->user_id === $user->id
            && in_array($this->status, [ReservationStatus::Returned, ReservationStatus::Rejected, ReservationStatus::Cancelled], true)
            && ($this->status !== ReservationStatus::Returned || $this->ends_at->isPast());
    }

    public function isOverdue(): bool
    {
        return $this->status === ReservationStatus::CheckedOut && $this->ends_at->isPast();
    }

    // ---------- scopes ----------
    /** Approved or checked out: the statuses that occupy the calendar. */
    public function scopeBlocking(Builder $q): Builder
    {
        return $q->whereIn('status', ReservationStatus::blocking());
    }

    public function scopeCheckedOut(Builder $q): Builder
    {
        return $q->where('status', ReservationStatus::CheckedOut);
    }

    /** starts_at < new end AND ends_at > new start. */
    public function scopeOverlapping(Builder $q, Carbon|\DateTimeInterface $start, Carbon|\DateTimeInterface $end): Builder
    {
        return $q->where('starts_at', '<', $end)->where('ends_at', '>', $start);
    }

    /** Is this checkout authorized right now? (checked_out AND now between start and end) */
    public function isAuthorizedNow(): bool
    {
        $now = now();

        return $this->status === ReservationStatus::CheckedOut
            && $now->gte($this->starts_at) && $now->lt($this->ends_at);
    }
}
