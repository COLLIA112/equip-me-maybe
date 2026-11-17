<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Department;
use App\Models\Document;
use App\Models\Equipment;
use App\Models\Lab;
use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReservationConvenienceTest extends TestCase
{
    use RefreshDatabase;

    public static function roles(): array
    {
        return array_combine(array_column(Role::cases(), 'value'), array_map(fn (Role $role) => [$role->value], Role::cases()));
    }

    private function fixture(string $role): array
    {
        $department = Department::factory()->create();
        $lab = Lab::factory()->for($department)->create();
        $item = Equipment::factory()->for($lab)->create();
        $user = User::factory()->create(['role' => Role::from($role), 'department_id' => $department->id, 'lab_id' => $role === 'lab_manager' ? $lab->id : null]);

        return [$user, $item];
    }

    #[DataProvider('roles')]
    public function test_reserve_again_is_only_for_the_owner_borrower_of_a_past_terminal_booking(string $role): void
    {
        [$user, $item] = $this->fixture($role);
        $reservation = Reservation::factory()->for($item)->for($user, 'user')->returned()->create(['starts_at' => now()->subDays(2), 'ends_at' => now()->subDays(2)->addHours(2)]);
        $response = $this->actingAs($user)->get(route('reservations.again', $reservation));
        if (in_array($role, ['student', 'faculty'], true)) {
            $response->assertOk()->assertSee('Reserve again')->assertViewHas('equipment', fn ($equipment) => $equipment->id === $item->id)
                ->assertViewHas('prefill', fn ($prefill) => Carbon::parse($prefill['starts_at'])->isFuture());
            $this->assertDatabaseCount('reservations', 1);
        } else {
            $response->assertForbidden();
        }
    }

    public function test_reserve_again_denies_another_owner_and_nonterminal_booking(): void
    {
        [$user, $item] = $this->fixture('student');
        $other = Reservation::factory()->for($item)->returned()->create(['ends_at' => now()->subDay()]);
        $pending = Reservation::factory()->for($item)->for($user, 'user')->create();
        $this->actingAs($user)->get(route('reservations.again', $other))->assertForbidden();
        $this->get(route('reservations.again', $pending))->assertForbidden();
    }

    #[DataProvider('roles')]
    public function test_calendar_export_is_approved_only_and_authorized_for_each_role(string $role): void
    {
        [$user, $item] = $this->fixture($role);
        $reservation = Reservation::factory()->for($item)->for($user, 'user')->approved()->create();
        $response = $this->actingAs($user)->get(route('reservations.calendar', $reservation));
        if ($role === 'security_auditor') {
            $response->assertForbidden();
        } else {
            $response->assertOk()->assertHeader('Content-Type', 'text/calendar; charset=UTF-8')->assertSee('BEGIN:VCALENDAR', false)->assertSee('DTSTART:', false);
        }
        $reservation->update(['status' => 'pending']);
        $this->get(route('reservations.calendar', $reservation))->assertForbidden();
    }

    public function test_calendar_escapes_untrusted_text_uses_utc_and_denies_other_borrowers(): void
    {
        [$user, $item] = $this->fixture('student');
        $item->update(['name' => "Camera, one; \\ \r\nBEGIN:BAD"]);
        $reservation = Reservation::factory()->for($item)->for($user, 'user')->approved()->create(['starts_at' => '2027-01-10 09:00:00', 'ends_at' => '2027-01-10 10:00:00']);
        $response = $this->actingAs($user)->get(route('reservations.calendar', $reservation))->assertOk();
        $content = $response->getContent();
        $this->assertStringContainsString('DTSTART:20270110T090000Z', $content);
        $this->assertStringContainsString('Camera\\, one\\;', $content);
        $this->assertStringNotContainsString("\r\nBEGIN:BAD", $content);
        $this->assertStringNotContainsString("\n", str_replace("\r\n", '', $content));
        $other = User::factory()->student()->create();
        $this->actingAs($other)->get(route('reservations.calendar', $reservation))->assertForbidden();
    }

    #[DataProvider('roles')]
    public function test_bulk_actions_and_counter_require_a_lab_manager(string $role): void
    {
        [$user, $item] = $this->fixture($role);
        $reservation = Reservation::factory()->for($item)->create();
        $bulk = $this->actingAs($user)->postJson(route('reservations.bulk'), ['action' => 'approve', 'reservation_ids' => [$reservation->id]]);
        $counter = $this->get(route('reservations.counter'));
        if ($role === 'lab_manager') {
            $bulk->assertOk()->assertJsonPath('succeeded', 1);
            $counter->assertOk()->assertSee('Equipment code or reservation number');
        } else {
            $bulk->assertForbidden();
            $counter->assertForbidden();
        }
    }

    public function test_bulk_approval_reports_partial_success_and_never_bypasses_compliance(): void
    {
        [$manager, $item] = $this->fixture('lab_manager');
        $ready = Reservation::factory()->for($item)->create();
        $blockedItem = Equipment::factory()->for($item->lab)->create();
        Document::factory()->for($blockedItem)->required()->create(['title' => 'Safety induction']);
        $blocked = Reservation::factory()->for($blockedItem)->create();
        $this->actingAs($manager)->postJson(route('reservations.bulk'), ['action' => 'approve', 'reservation_ids' => [$ready->id, $blocked->id]])
            ->assertOk()->assertJsonPath('succeeded', 1)->assertJsonPath('failed', 1)->assertJsonPath('errors.'.$blocked->id.'.0', 'The borrower must acknowledge: Safety induction.');
        $this->assertDatabaseHas('reservations', ['id' => $ready->id, 'status' => 'approved']);
        $this->assertDatabaseHas('reservations', ['id' => $blocked->id, 'status' => 'pending']);
    }

    public function test_foreign_bulk_selection_aborts_the_whole_batch_and_invalid_input_is_rejected(): void
    {
        [$manager, $item] = $this->fixture('lab_manager');
        $own = Reservation::factory()->for($item)->create();
        $foreign = Reservation::factory()->create();
        $this->actingAs($manager)->postJson(route('reservations.bulk'), ['action' => 'reject', 'reservation_ids' => [$own->id, $foreign->id]])->assertForbidden();
        $this->assertDatabaseHas('reservations', ['id' => $own->id, 'status' => 'pending']);
        $this->postJson(route('reservations.bulk'), ['action' => 'delete', 'reservation_ids' => 'all'])->assertUnprocessable();
        $this->postJson(route('reservations.bulk'), ['action' => 'approve', 'reservation_ids' => array_fill(0, 51, $own->id)])->assertUnprocessable();
        $this->postJson(route('reservations.counter.lookup'), ['code' => ['bad']])->assertUnprocessable();
    }

    public function test_counter_finds_own_lab_booking_by_code_and_never_exposes_another_lab(): void
    {
        [$manager, $item] = $this->fixture('lab_manager');
        $own = Reservation::factory()->for($item)->approved()->create();
        $foreign = Reservation::factory()->approved()->create();
        $this->actingAs($manager)->get(route('reservations.counter', ['code' => $item->qr_code]))->assertOk()->assertSee('Reservation #'.$own->id)->assertSee('Hand over');
        $this->get(route('reservations.counter', ['code' => (string) $foreign->id]))->assertOk()->assertDontSee('Reservation #'.$foreign->id)->assertSee('No approved or checked-out reservation');
    }

    #[DataProvider('roles')]
    public function test_return_condition_is_manager_only_and_damage_creates_linked_maintenance(string $role): void
    {
        [$user, $item] = $this->fixture($role);
        $reservation = Reservation::factory()->for($item)->checkedOut()->create();
        $response = $this->actingAs($user)->patchJson(route('reservations.return', $reservation), ['return_condition' => 'damaged', 'return_notes' => 'Lens housing cracked.']);
        if ($role === 'lab_manager') {
            $response->assertRedirect();
            $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'returned', 'return_condition' => 'damaged']);
            $this->assertDatabaseHas('maintenance_logs', ['equipment_id' => $item->id, 'reservation_id' => $reservation->id, 'reported_by' => $user->id]);
            $this->assertDatabaseHas('equipment', ['id' => $item->id, 'status' => 'under_repair']);
        } else {
            $response->assertForbidden();
        }
    }

    public function test_return_requires_notes_for_damage_and_good_condition_keeps_existing_repair_status(): void
    {
        [$manager, $item] = $this->fixture('lab_manager');
        $reservation = Reservation::factory()->for($item)->checkedOut()->create();
        $this->actingAs($manager)->patchJson(route('reservations.return', $reservation), ['return_condition' => 'missing_parts'])->assertUnprocessable()->assertJsonValidationErrors('return_notes');
        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'checked_out']);
        $item->update(['status' => 'under_repair']);
        $this->patch(route('reservations.return', $reservation), ['return_condition' => 'good'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('equipment', ['id' => $item->id, 'status' => 'under_repair']);
    }
}
