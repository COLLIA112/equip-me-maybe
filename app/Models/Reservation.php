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
        'status', 'handed_over_at', 'returned_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReservationStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'handed_over_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
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
        return $this->status === ReservationStatus::CheckedOut
            && now()->betweenIncluded($this->starts_at, $this->ends_at);
    }
}
