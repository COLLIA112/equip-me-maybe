<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Equipment;
use App\Models\LabClosure;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class QualityOfLifeIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_closures_cannot_silently_displace_approved_bookings_and_requests_recheck_closures(): void
    {
        $item = Equipment::factory()->create();
        $manager = User::factory()->labManager($item->lab)->create();
        $student = User::factory()->student()->create();
        $start = now()->addDays(3)->startOfHour();
        $end = $start->copy()->addHours(2);
        $booking = Reservation::factory()->for($item)->approved()->create(['starts_at' => $start, 'ends_at' => $end]);
        $payload = ['lab_id' => $item->lab_id, 'starts_at' => $start->toDateTimeString(), 'ends_at' => $end->toDateTimeString(), 'reason' => 'Exam closure'];
        $this->actingAs($manager)->post(route('lab-closures.store'), $payload)->assertSessionHasErrors('starts_at');
        $this->assertDatabaseCount('lab_closures', 0);
        $booking->update(['status' => 'cancelled']);
        $this->post(route('lab-closures.store'), $payload)->assertSessionHasNoErrors();
        $this->actingAs($student)->post(route('reservations.store', $item), $payload)->assertSessionHasErrors('starts_at');
        $pending = Reservation::factory()->for($item)->create(['starts_at' => $start, 'ends_at' => $end]);
        $this->actingAs($manager)->patch(route('reservations.approve', $pending))->assertSessionHasErrors('starts_at');
        $this->assertDatabaseHas('reservations', ['id' => $pending->id, 'status' => 'pending']);
        $this->actingAs($student)->post(route('reservations.store', $item), ['starts_at' => $end->toDateTimeString(), 'ends_at' => $end->copy()->addHour()->toDateTimeString()])->assertSessionHasNoErrors();
    }

    public function test_updating_closure_into_an_approved_window_is_rejected_without_moving_it(): void
    {
        $item = Equipment::factory()->create();
        $manager = User::factory()->labManager($item->lab)->create();
        $start = now()->addDays(3)->startOfHour();
        Reservation::factory()->for($item)->approved()->create(['starts_at' => $start, 'ends_at' => $start->copy()->addHour()]);
        $closure = LabClosure::create(['lab_id' => $item->lab_id, 'starts_at' => $start->copy()->addDay(), 'ends_at' => $start->copy()->addDay()->addHour(), 'reason' => 'Existing closure']);
        $this->actingAs($manager)->patch(route('lab-closures.update', $closure), ['lab_id' => $item->lab_id, 'starts_at' => $start->toDateTimeString(), 'ends_at' => $start->copy()->addHour()->toDateTimeString(), 'reason' => 'Move closure'])->assertSessionHasErrors('starts_at');
        $this->assertTrue($closure->fresh()->starts_at->equalTo($start->copy()->addDay()));
    }

    public function test_unassigned_management_roles_cannot_read_activity_without_an_equipment_record(): void
    {
        ActivityLog::create(['event' => 'Private event', 'details' => ['note' => 'No lab should see this']]);
        foreach ([Role::LabManager, Role::DepartmentHead] as $role) {
            $user = User::factory()->create(['role' => $role, 'lab_id' => null, 'department_id' => null]);
            $this->actingAs($user)->get(route('activities.index'))->assertOk()->assertDontSee('No lab should see this');
        }
    }

    public function test_clones_and_csv_imports_have_visible_actor_attribution(): void
    {
        $item = Equipment::factory()->create();
        $manager = User::factory()->labManager($item->lab)->create();
        $this->actingAs($manager)->post(route('equipment.tools.clone', $item), ['name' => 'Copied camera', 'serial_number' => 'CLONE-AUDIT'])->assertSessionHasNoErrors();
        $clone = Equipment::whereSerialNumber('CLONE-AUDIT')->firstOrFail();
        $this->assertDatabaseHas('activity_logs', ['equipment_id' => $clone->id, 'actor_id' => $manager->id, 'event' => 'equipment.cloned']);
        $this->post(route('equipment.tools.import'), ['file' => UploadedFile::fake()->createWithContent('equipment.csv', "name,serial_number\nImported camera,IMPORT-AUDIT\n")])->assertSessionHasNoErrors();
        $import = Equipment::whereSerialNumber('IMPORT-AUDIT')->firstOrFail();
        $this->assertDatabaseHas('activity_logs', ['equipment_id' => $import->id, 'actor_id' => $manager->id, 'event' => 'equipment.imported']);
        $this->get(route('activities.index'))->assertOk()->assertSee('Copied camera')->assertSee('Imported camera')->assertSee($manager->name);
    }

    public function test_head_report_date_choices_are_remembered_and_invalid_ranges_are_not_saved(): void
    {
        $head = User::factory()->departmentHead()->create();
        $this->actingAs($head)->get(route('department.index', ['period' => 'week']))->assertOk();
        $this->get(route('department.index'))->assertOk()->assertViewHas('range', fn ($range) => $range['period'] === 'week');
        $this->get(route('department.index', ['period' => 'custom', 'from' => '2026-11-20', 'to' => '2026-11-01']))->assertOk()->assertViewHas('range', fn ($range) => $range['period'] === 'month');
        $this->assertSame(['period' => 'month'], $head->fresh()->preferences['lists']['department.index']);
        $this->get(route('department.index', ['clear' => 1]))->assertRedirect(route('department.index'));
    }

    public function test_reservation_date_guidance_labels_and_uses_the_application_timezone(): void
    {
        config(['app.timezone' => 'UTC']);
        $item = Equipment::factory()->create();
        $this->actingAs(User::factory()->student()->create())->get(route('reservations.create', $item))->assertOk()
            ->assertSee('data-reservation-timezone="UTC"', false)->assertSee('Times are shown in UTC');
    }
}
