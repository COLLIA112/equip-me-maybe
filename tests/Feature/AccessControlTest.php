<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\Lab;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('equipment.index'))->assertRedirect(route('login'));
        $this->get(route('reservations.queue'))->assertRedirect(route('login'));
    }

    public function test_student_gets_403_on_manager_routes(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)->get(route('equipment.create'))->assertForbidden();
        $this->actingAs($student)->get(route('maintenance.index'))->assertForbidden();
        $this->actingAs($student)->get(route('department.index'))->assertForbidden();
        $this->actingAs($student)->get(route('reservations.queue'))->assertForbidden();
    }

    public function test_student_cannot_approve_a_reservation(): void
    {
        $reservation = Reservation::factory()->create();

        $this->actingAs($reservation->user)
            ->patch(route('reservations.approve', $reservation))
            ->assertForbidden();
    }

    public function test_lab_manager_cannot_edit_another_labs_equipment(): void
    {
        $myLab = Lab::factory()->create();
        $otherLab = Lab::factory()->create();
        $manager = User::factory()->labManager($myLab)->create();
        $foreign = Equipment::factory()->for($otherLab)->create();

        $this->actingAs($manager)
            ->put(route('equipment.update', $foreign), ['name' => 'Hacked', 'serial_number' => 'SN-HACK-1'])
            ->assertForbidden();

        $this->assertNotSame('Hacked', $foreign->fresh()->name);
    }

    public function test_lab_manager_can_edit_own_labs_equipment(): void
    {
        $lab = Lab::factory()->create();
        $manager = User::factory()->labManager($lab)->create();
        $item = Equipment::factory()->for($lab)->create();

        $this->actingAs($manager)
            ->put(route('equipment.update', $item), ['name' => 'Renamed', 'serial_number' => $item->serial_number])
            ->assertRedirect(route('equipment.show', $item));

        $this->assertSame('Renamed', $item->fresh()->name);
    }

    public function test_security_auditor_has_no_web_access(): void
    {
        $auditor = User::factory()->securityAuditor()->create();
        $item = Equipment::factory()->create();

        $this->actingAs($auditor)->get(route('equipment.show', $item))->assertForbidden();
    }
}
