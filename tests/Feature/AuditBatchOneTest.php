<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Models\Department;
use App\Models\Equipment;
use App\Models\Lab;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditBatchOneTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_cancel_an_approved_booking_with_a_reason(): void
    {
        $lab = Lab::factory()->create();
        $manager = User::factory()->labManager($lab)->create();
        $reservation = Reservation::factory()->for(Equipment::factory()->for($lab)->create())->approved()->create();

        $this->actingAs($manager)
            ->patch(route('reservations.revoke', $reservation), ['cancellation_reason' => 'Equipment is being repaired.'])
            ->assertSessionHasNoErrors();

        $reservation->refresh();
        $this->assertSame(ReservationStatus::Cancelled, $reservation->status);
        $this->assertSame('Equipment is being repaired.', $reservation->cancellation_reason);
        $this->assertSame($manager->id, $reservation->cancelled_by);
    }

    public function test_revoke_needs_a_reason_and_only_works_on_approved_bookings_in_own_lab(): void
    {
        $lab = Lab::factory()->create();
        $manager = User::factory()->labManager($lab)->create();
        $item = Equipment::factory()->for($lab)->create();
        $approved = Reservation::factory()->for($item)->approved()->create();
        $pending = Reservation::factory()->for($item)->create();
        $foreign = Reservation::factory()->approved()->create();

        $this->actingAs($manager)->patch(route('reservations.revoke', $approved), ['cancellation_reason' => ''])->assertSessionHasErrors('cancellation_reason');
        $this->actingAs($manager)->patch(route('reservations.revoke', $pending), ['cancellation_reason' => 'Some reason'])->assertForbidden();
        $this->actingAs($manager)->patch(route('reservations.revoke', $foreign), ['cancellation_reason' => 'Some reason'])->assertForbidden();
    }

    public function test_promoted_borrower_cannot_approve_their_own_reservation(): void
    {
        $lab = Lab::factory()->create();
        $item = Equipment::factory()->for($lab)->create();
        $user = User::factory()->student()->create();
        $reservation = Reservation::factory()->for($item)->for($user, 'user')->create();

        $user->forceFill(['role' => Role::LabManager, 'lab_id' => $lab->id])->save();

        $this->actingAs($user)->patch(route('reservations.approve', $reservation))->assertForbidden();
    }

    public function test_head_cannot_move_a_user_with_open_reservations_to_a_non_borrower_role(): void
    {
        $department = Department::factory()->create();
        $head = User::factory()->departmentHead($department)->create();
        $student = User::factory()->student()->create();
        Reservation::factory()->for($student, 'user')->create();

        $this->actingAs($head)
            ->patch(route('department.users.update', $student), ['role' => 'security_auditor'])
            ->assertSessionHasErrors('role');

        $this->assertSame(Role::Student, $student->fresh()->role);
    }

    public function test_borrower_cannot_submit_the_same_overlapping_request_twice(): void
    {
        $student = User::factory()->student()->create();
        $item = Equipment::factory()->create();
        $payload = [
            'starts_at' => now()->addDays(3)->setTime(9, 0)->toDateTimeString(),
            'ends_at' => now()->addDays(3)->setTime(11, 0)->toDateTimeString(),
        ];

        $this->actingAs($student)->post(route('reservations.store', $item), $payload)->assertSessionHasNoErrors();
        $this->actingAs($student)->post(route('reservations.store', $item), $payload)->assertSessionHasErrors('starts_at');

        $this->assertSame(1, Reservation::where('user_id', $student->id)->count());
    }

    public function test_deleted_account_email_can_be_registered_again(): void
    {
        $user = User::factory()->student()->create(['email' => 'again@example.com']);

        $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'password'])->assertSessionHasNoErrors();

        $this->post('/register', [
            'name' => 'Same Person',
            'email' => 'again@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasNoErrors();

        $this->assertAuthenticated();
    }
}