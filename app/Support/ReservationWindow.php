<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Pure value object for the overlap rule. No database needed, so it is unit testable.
 * Two windows overlap when this.start < other.end AND this.end > other.start.
 * Back to back windows (one ends exactly when the next starts) do NOT overlap.
 */
final class ReservationWindow
{
    public readonly CarbonImmutable $start;
    public readonly CarbonImmutable $end;

    public function __construct(CarbonInterface $start, CarbonInterface $end)
    {
        $this->start = CarbonImmutable::instance($start);
        $this->end = CarbonImmutable::instance($end);

        if ($this->end->lessThanOrEqualTo($this->start)) {
            throw new InvalidArgumentException('A reservation must end after it starts.');
        }
    }

    public function overlaps(self $other): bool
    {
        return $this->start->lt($other->end) && $this->end->gt($other->start);
    }
}
