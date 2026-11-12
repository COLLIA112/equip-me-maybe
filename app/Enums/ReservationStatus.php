<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case CheckedOut = 'checked_out';
    case Returned = 'returned';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    /** Statuses that occupy the calendar and can cause an overlap. */
    public static function blocking(): array
    {
        return [self::Approved, self::CheckedOut];
    }

    public function label(): string
    {
        return ucfirst(str_replace('_', ' ', $this->value));
    }
}
