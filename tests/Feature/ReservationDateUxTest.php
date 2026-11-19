<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReservationDateUxTest extends TestCase
{
    use RefreshDatabase;

    public static function roles(): array
    {
        return [['student', true], ['faculty', true], ['labManager', false], ['departmentHead', false], ['securityAuditor', false]];
    }

    #[DataProvider('roles')]
    public function test_only_borrowers_can_open_the_date_picker(string $role, bool $allowed): void
    {
        $user = User::factory()->{$role}()->create();
        $item = Equipment::factory()->create();
        $response = $this->actingAs($user)->get(route('reservations.create', $item));

        if (! $allowed) {
            $response->assertForbidden();
            return;
        }

        $response->assertOk()->assertSee('data-reservation-dates', false)
            ->assertSee('min="', false)->assertSee('data-booked-windows', false)
            ->assertSee('Reservation times are checked again when you submit.');
    }

    public function test_reservation_form_preserves_old_dates_and_names_both_dates_for_cross_day_bookings(): void
    {
        $item = Equipment::factory()->create();
        $start = now()->addDays(4)->setTime(23, 0);
        $end = $start->copy()->addHours(2);
        Reservation::factory()->for($item)->approved()->create(['starts_at' => $start, 'ends_at' => $end]);
        $oldStart = now()->addDays(6)->format('Y-m-d\TH:i');
        $oldEnd = now()->addDays(6)->addHours(3)->format('Y-m-d\TH:i');

        $this->actingAs(User::factory()->student()->create())
            ->withSession(['_old_input' => ['starts_at' => $oldStart, 'ends_at' => $oldEnd]])
            ->get(route('reservations.create', $item))->assertOk()
            ->assertSee('value="'.$oldStart.'"', false)->assertSee('value="'.$oldEnd.'"', false)
            ->assertSee($start->format('M j, Y g:i A'))->assertSee($end->format('M j, Y g:i A'));
    }

    #[DataProvider('roles')]
    public function test_invalid_dates_cannot_bypass_server_validation_for_any_role(string $role, bool $allowed): void
    {
        $item = Equipment::factory()->create();
        $user = User::factory()->{$role}()->create();
        $response = $this->actingAs($user)->post(route('reservations.store', $item), [
            'starts_at' => now()->subDay()->toDateTimeString(),
            'ends_at' => 'invalid-end-date',
        ]);

        if ($allowed) {
            $response->assertSessionHasErrors(['starts_at', 'ends_at']);
        } else {
            $response->assertForbidden();
        }
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_cross_day_overlap_message_contains_the_conflicting_full_window(): void
    {
        $item = Equipment::factory()->create();
        $start = now()->addDays(4)->setTime(23, 0);
        $end = $start->copy()->addHours(2);
        Reservation::factory()->for($item)->approved()->create(['starts_at' => $start, 'ends_at' => $end]);

        $response = $this->actingAs(User::factory()->faculty()->create())->post(route('reservations.store', $item), [
            'starts_at' => $start->copy()->addMinutes(30)->toDateTimeString(),
            'ends_at' => $end->copy()->addHour()->toDateTimeString(),
        ]);
        $response->assertSessionHasErrors(['starts_at' => 'This item is already booked from '.$start->format('M j, Y g:i A').' to '.$end->format('M j, Y g:i A').'.']);
        $this->assertDatabaseCount('reservations', 1);
    }
}
