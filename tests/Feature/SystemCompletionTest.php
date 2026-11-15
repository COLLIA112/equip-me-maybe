<?php

namespace Tests\Feature;

use App\Enums\EquipmentStatus;
use App\Enums\ReservationStatus;
use App\Models\Department;
use App\Models\Document;
use App\Models\Equipment;
use App\Models\Lab;
use App\Models\MaintenanceLog;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SystemCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolving_the_last_breakdown_keeps_a_checked_out_item_reserved(): void
    {
        $item = Equipment::factory()->underRepair()->create();
        $manager = User::factory()->labManager($item->lab)->create();
        Reservation::factory()->for($item)->checkedOut()->create();
        [$first, $last] = MaintenanceLog::factory()->count(2)->for($item)->unresolved()->create([
            'reported_by' => $manager->id,
        ])->all();

        $this->actingAs($manager)->patch(route('maintenance.resolve', $first))->assertSessionHasNoErrors();
        $this->assertSame(EquipmentStatus::UnderRepair, $item->fresh()->status);
        $this->patch(route('maintenance.resolve', $last))->assertSessionHasNoErrors();
        $this->assertSame(EquipmentStatus::Reserved, $item->fresh()->status);
    }

    public function test_head_can_see_equipment_and_maintenance_only_in_their_department(): void
    {
        $department = Department::factory()->create();
        $head = User::factory()->departmentHead($department)->create();
        $ownLab = Lab::factory()->for($department)->create();
        $own = Equipment::factory()->for($ownLab)->create(['name' => 'In scope equipment']);
        $other = Equipment::factory()->create(['name' => 'Out of scope equipment']);

        $this->actingAs($head)->get(route('equipment.index'))->assertOk()
            ->assertSee($own->name)->assertDontSee($other->name);
        $this->get(route('equipment.show', $other))->assertForbidden();
        $this->get(route('department.breakdowns', $other))->assertForbidden();
        $this->get(route('maintenance.index'))->assertOk()
            ->assertSee($own->name)->assertDontSee($other->name);
        $this->post(route('maintenance.store'), ['equipment_id' => $other->id, 'issue' => 'Outside the department'])
            ->assertForbidden();
        $this->post(route('maintenance.store'), ['equipment_id' => $own->id, 'issue' => 'Cracked lens'])
            ->assertSessionHasNoErrors();
        $this->assertSame(EquipmentStatus::UnderRepair, $own->fresh()->status);
    }

    public function test_equipment_uses_the_public_photo_disk_and_validates_uploads(): void
    {
        config(['filesystems.default' => 'local', 'filesystems.disks.public.url' => 'https://cdn.example.test/photos']);
        Storage::fake('public', ['url' => 'https://cdn.example.test/photos']);
        $lab = Lab::factory()->create();
        $manager = User::factory()->labManager($lab)->create();
        $payload = ['name' => 'Test camera', 'serial_number' => 'SN-PHOTO'];

        $this->actingAs($manager)->post(route('equipment.store'), $payload + [
            'photo' => UploadedFile::fake()->create('manual.pdf', 25, 'application/pdf'),
        ])->assertSessionHasErrors('photo');
        $this->post(route('equipment.store'), $payload + [
            'photo' => UploadedFile::fake()->image('oversized.png')->size(2049),
        ])->assertSessionHasErrors('photo');
        $this->assertDatabaseCount('equipment', 0);

        $this->post(route('equipment.store'), $payload + [
            'photo' => UploadedFile::fake()->image('camera.png'),
        ])->assertSessionHasNoErrors();

        $item = Equipment::firstOrFail();
        Storage::disk('public')->assertExists($item->photo_path);
        $this->assertSame('https://cdn.example.test/photos/'.$item->photo_path, $item->photo_url);
    }

    public function test_qr_label_requires_management_permission(): void
    {
        $item = Equipment::factory()->create();
        $manager = User::factory()->labManager($item->lab)->create();
        $head = User::factory()->departmentHead($item->lab->department)->create();
        $outsider = User::factory()->labManager()->create();
        $student = User::factory()->student()->create();

        $this->actingAs($student)->get(route('equipment.show', $item))->assertOk()
            ->assertSee('QR code for '.$item->qr_code)->assertSee('<svg', false)->assertDontSee('Print QR label');
        $this->get(route('equipment.qr-label', $item))->assertForbidden();
        $this->actingAs($outsider)->get(route('equipment.qr-label', $item))->assertForbidden();
        $this->actingAs($manager)->get(route('equipment.qr-label', $item))->assertOk()
            ->assertSee($item->qr_code)->assertSee('<svg', false);
        $this->actingAs($head)->get(route('equipment.qr-label', $item))->assertOk();
    }

    public function test_private_documents_have_no_public_file_and_require_an_authorized_download(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $item = Equipment::factory()->create();
        $manager = User::factory()->labManager($item->lab)->create();
        Storage::disk('local')->put('documents/private-check.pdf', '%PDF-1.4');
        $document = Document::factory()->for($item)->create(['file_path' => 'documents/private-check.pdf']);
        Storage::disk('public')->assertMissing($document->file_path);

        $this->get('/storage/'.$document->file_path)->assertNotFound();
        $this->get(route('documents.download', $document))->assertRedirect(route('login'));
        $this->actingAs($manager)->get(route('documents.download', $document))->assertDownload($document->original_name);
    }

    public function test_reservation_timeline_is_scoped_and_displays_the_recorded_steps(): void
    {
        $reservation = Reservation::factory()->returned()->create();
        $outsider = User::factory()->student()->create();
        $this->actingAs($outsider)->get(route('reservations.show', $reservation))->assertForbidden();
        $this->actingAs($reservation->user)->get(route('reservations.show', $reservation))->assertOk()
            ->assertSeeInOrder(['Reservation timeline', 'Requested', 'Approved', 'Checked out', 'Returned']);
    }

    public function test_priority_displaces_only_overlapping_pending_student_requests(): void
    {
        $item = Equipment::factory()->create();
        $student = User::factory()->student()->create();
        $faculty = User::factory()->faculty()->create();
        $start = now()->addDay()->startOfDay()->addHours(9);
        $end = $start->copy()->addHours(2);
        $window = ['starts_at' => $start->toDateTimeString(), 'ends_at' => $end->toDateTimeString()];
        $studentPending = Reservation::factory()->for($item)->for($student, 'user')->create($window);
        $facultyPending = Reservation::factory()->for($item)->for($faculty, 'user')->create($window);
        $backToBack = Reservation::factory()->for($item)->for($student, 'user')->create([
            'starts_at' => $end,
            'ends_at' => $end->copy()->addHour(),
        ]);

        $this->actingAs($student)->post(route('reservations.store', $item), $window + ['is_class_session' => 1])
            ->assertSessionHasErrors('is_class_session');
        $this->actingAs($faculty)->post(route('reservations.store', $item), $window)
            ->assertSessionHasNoErrors();
        $this->assertSame(ReservationStatus::Pending, $studentPending->fresh()->status);

        $this->post(route('reservations.store', $item), $window + ['is_class_session' => 1])
            ->assertSessionHasNoErrors();

        $this->assertSame(ReservationStatus::Rejected, $studentPending->fresh()->status);
        $this->assertSame(ReservationStatus::Pending, $facultyPending->fresh()->status);
        $this->assertSame(ReservationStatus::Pending, $backToBack->fresh()->status);
        $this->assertTrue($faculty->reservations()->where('is_class_session', true)->sole()->is_class_session);
    }

    public function test_priority_leaves_all_requests_alone_when_a_blocking_booking_exists(): void
    {
        $faculty = User::factory()->faculty()->create();
        $start = now()->addDay()->setTime(9, 0);
        $window = ['starts_at' => $start->toDateTimeString(), 'ends_at' => $start->copy()->addHours(2)->toDateTimeString()];

        foreach (ReservationStatus::blocking() as $status) {
            $item = Equipment::factory()->create();
            $blocker = Reservation::factory()->for($item)->status($status)->create($window);
            $studentPending = Reservation::factory()->for($item)->create($window);

            $this->actingAs($faculty)->post(route('reservations.store', $item), $window + ['is_class_session' => 1])
                ->assertSessionHasErrors('starts_at');

            $this->assertSame($status, $blocker->fresh()->status);
            $this->assertSame(ReservationStatus::Pending, $studentPending->fresh()->status);
            $this->assertCount(2, $item->reservations);
        }
    }
}
