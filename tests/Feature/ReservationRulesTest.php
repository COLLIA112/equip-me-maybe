<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationRulesTest extends TestCase
{
    use RefreshDatabase;

    private function book(User $user, Equipment $item, string $start, string $end)
    {
        return $this->actingAs($user)->post(route('reservations.store', $item), [
            'starts_at' => $start,
            'ends_at' => $end,
        ]);
    }

    public function test_item_under_repair_cannot_be_reserved(): void
    {
        $student = User::factory()->student()->create();
        $item = Equipment::factory()->underRepair()->create();

        $this->book($student, $item, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString())
            ->assertSessionHasErrors('equipment');

        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_overlapping_request_is_rejected_but_back_to_back_is_allowed(): void
    {
        $student = User::factory()->student()->create();
        $item = Equipment::factory()->create();

        $day = now()->addDays(3)->startOfDay();
        Reservation::factory()->for($item)->approved()->create([
            'starts_at' => $day->copy()->setTime(9, 0),
            'ends_at' => $day->copy()->setTime(13, 0),
        ]);

        // One minute of overlap: rejected, and the message names the conflicting window.
        $this->book($student, $item, $day->copy()->setTime(12, 59)->toDateTimeString(), $day->copy()->setTime(15, 0)->toDateTimeString())
            ->assertSessionHasErrors('starts_at');

        // Starts exactly when the other one ends: allowed.
        $this->book($student, $item, $day->copy()->setTime(13, 0)->toDateTimeString(), $day->copy()->setTime(15, 0)->toDateTimeString())
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('reservations', 2);
    }

    public function test_pending_reservations_do_not_block_the_calendar(): void
    {
        $student = User::factory()->student()->create();
        $item = Equipment::factory()->create();

        $start = now()->addDays(2)->setTime(9, 0);
        Reservation::factory()->for($item)->create(['starts_at' => $start, 'ends_at' => $start->copy()->addHours(4)]);

        $this->book($student, $item, $start->toDateTimeString(), $start->copy()->addHours(2)->toDateTimeString())
            ->assertSessionHasNoErrors();
    }

    public function test_faculty_can_reserve_but_security_auditor_cannot(): void
    {
        $item = Equipment::factory()->create();
        $start = now()->addDay()->setTime(9, 0)->toDateTimeString();
        $end = now()->addDay()->setTime(11, 0)->toDateTimeString();

        $this->book(User::factory()->faculty()->create(), $item, $start, $end)->assertSessionHasNoErrors();
        $this->book(User::factory()->securityAuditor()->create(), $item, $start, $end)->assertForbidden();
    }

    public function test_breakdown_report_flips_status_and_resolving_reopens_the_item(): void
    {
        $lab = \App\Models\Lab::factory()->create();
        $manager = User::factory()->labManager($lab)->create();
        $item = Equipment::factory()->for($lab)->create();

        $this->actingAs($manager)->post(route('maintenance.store'), ['equipment_id' => $item->id, 'issue' => 'Lens cracked']);
        $this->assertTrue($item->fresh()->status->blocksReservation());

        $log = $item->maintenanceLogs()->firstOrFail();
        $this->actingAs($manager)->patch(route('maintenance.resolve', $log));

        $this->assertFalse($item->fresh()->status->blocksReservation());
    }
}
