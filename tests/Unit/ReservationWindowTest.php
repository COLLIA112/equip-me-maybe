<?php

namespace Tests\Unit;

use App\Support\ReservationWindow;
use Carbon\Carbon;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ReservationWindowTest extends TestCase
{
    private function window(string $start, string $end): ReservationWindow
    {
        return new ReservationWindow(Carbon::parse($start), Carbon::parse($end));
    }

    public function test_back_to_back_windows_do_not_overlap(): void
    {
        $a = $this->window('2026-12-01 09:00', '2026-12-01 13:00');
        $b = $this->window('2026-12-01 13:00', '2026-12-01 17:00');

        $this->assertFalse($a->overlaps($b));
        $this->assertFalse($b->overlaps($a));
    }

    public function test_one_minute_overlap_is_rejected(): void
    {
        $a = $this->window('2026-12-01 09:00', '2026-12-01 13:00');
        $b = $this->window('2026-12-01 12:59', '2026-12-01 17:00');

        $this->assertTrue($a->overlaps($b));
        $this->assertTrue($b->overlaps($a));
    }

    public function test_a_window_inside_another_overlaps(): void
    {
        $outer = $this->window('2026-12-01 08:00', '2026-12-01 18:00');
        $inner = $this->window('2026-12-01 10:00', '2026-12-01 11:00');

        $this->assertTrue($outer->overlaps($inner));
    }

    public function test_end_must_be_after_start(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->window('2026-12-01 10:00', '2026-12-01 10:00');
    }
}
