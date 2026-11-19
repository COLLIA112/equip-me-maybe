<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\Equipment;
use App\Models\Lab;
use App\Models\MaintenanceLog;
use App\Models\Reservation;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EquipmentManagementTest extends TestCase
{
    use RefreshDatabase;

    /** A real 1x1 PNG, so the image rules pass without needing the GD extension. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private function png(string $name = 'photo.png', int $padBytes = 0): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode(self::PNG).str_repeat('0', $padBytes));
    }

    /** @return array{0: User, 1: Lab} */
    private function manager(): array
    {
        $lab = Lab::factory()->create();

        return [User::factory()->labManager($lab)->create(), $lab];
    }

    // ---------- create ----------

    public function test_lab_manager_can_create_equipment_with_tags_and_a_generated_code(): void
    {
        [$manager, $lab] = $this->manager();
        $tag = Tag::create(['name' => 'fragile']);

        $this->actingAs($manager)->post(route('equipment.store'), [
            'name' => 'Test Oscilloscope',
            'serial_number' => 'SN-TEST-0001',
            'tags' => [$tag->id],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $item = Equipment::where('serial_number', 'SN-TEST-0001')->firstOrFail();
        $this->assertSame($lab->id, $item->lab_id);
        $this->assertSame('available', $item->status->value);
        $this->assertMatchesRegularExpression('/^EQ-[A-Z0-9]{8}$/', $item->qr_code);
        $this->assertSame([$tag->id], $item->tags()->pluck('tags.id')->all());
    }

    public function test_lab_manager_cannot_create_equipment_in_another_lab(): void
    {
        [$manager, $myLab] = $this->manager();
        $otherLab = Lab::factory()->create();

        $this->actingAs($manager)->post(route('equipment.store'), [
            'lab_id' => $otherLab->id, // ignored: a manager's lab comes from their account
            'name' => 'Sneaky Item',
            'serial_number' => 'SN-SNEAK-1',
        ]);

        $this->assertSame($myLab->id, Equipment::where('serial_number', 'SN-SNEAK-1')->firstOrFail()->lab_id);
    }

    public function test_student_cannot_create_equipment(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->post(route('equipment.store'), ['name' => 'X', 'serial_number' => 'SN-X-1'])
            ->assertForbidden();
    }

    public function test_department_head_can_add_to_own_department_only(): void
    {
        $department = Department::factory()->create();
        $head = User::factory()->departmentHead($department)->create();
        $ownLab = Lab::factory()->for($department)->create();
        $foreignLab = Lab::factory()->create();

        $this->actingAs($head)
            ->post(route('equipment.store'), ['lab_id' => $ownLab->id, 'name' => 'Own', 'serial_number' => 'SN-OWN-1'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('equipment', ['serial_number' => 'SN-OWN-1', 'lab_id' => $ownLab->id]);

        $this->actingAs($head)
            ->post(route('equipment.store'), ['lab_id' => $foreignLab->id, 'name' => 'Foreign', 'serial_number' => 'SN-FOR-1'])
            ->assertForbidden();
        $this->assertDatabaseMissing('equipment', ['serial_number' => 'SN-FOR-1']);
    }

    public function test_department_head_must_choose_a_lab(): void
    {
        $head = User::factory()->departmentHead()->create();

        $this->actingAs($head)
            ->post(route('equipment.store'), ['name' => 'No Lab', 'serial_number' => 'SN-NOLAB-1'])
            ->assertSessionHasErrors('lab_id');
    }

    public function test_serial_number_must_be_unique(): void
    {
        [$manager, $lab] = $this->manager();
        Equipment::factory()->for($lab)->create(['serial_number' => 'SN-DUP-1']);

        $this->actingAs($manager)
            ->post(route('equipment.store'), ['name' => 'Copy', 'serial_number' => 'SN-DUP-1'])
            ->assertSessionHasErrors('serial_number');
    }

    public function test_every_item_gets_a_unique_qr_code(): void
    {
        $lab = Lab::factory()->create();

        $a = Equipment::create(['lab_id' => $lab->id, 'name' => 'A', 'serial_number' => 'SN-QR-A']);
        $b = Equipment::create(['lab_id' => $lab->id, 'name' => 'B', 'serial_number' => 'SN-QR-B']);

        $this->assertNotSame($a->qr_code, $b->qr_code);
        $this->assertMatchesRegularExpression('/^EQ-[A-Z0-9]{8}$/', $a->qr_code);
    }

    // ---------- photo upload ----------

    public function test_photo_is_stored_on_the_public_disk_and_exposes_a_url(): void
    {
        Storage::fake('public');
        [$manager] = $this->manager();

        $this->actingAs($manager)->post(route('equipment.store'), [
            'name' => 'With Photo',
            'serial_number' => 'SN-PHOTO-1',
            'photo' => $this->png(),
        ])->assertSessionHasNoErrors();

        $item = Equipment::where('serial_number', 'SN-PHOTO-1')->firstOrFail();
        Storage::disk('public')->assertExists($item->photo_path);
        $this->assertSame(Storage::disk('public')->url($item->photo_path), $item->photo_url);
    }

    public function test_item_without_a_photo_has_no_photo_url(): void
    {
        $this->assertNull(Equipment::factory()->create(['photo_path' => null])->photo_url);
    }

    public function test_wrong_photo_type_is_rejected(): void
    {
        Storage::fake('public');
        [$manager] = $this->manager();

        $this->actingAs($manager)->post(route('equipment.store'), [
            'name' => 'Bad Photo',
            'serial_number' => 'SN-BADPHOTO-1',
            'photo' => UploadedFile::fake()->create('manual.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('photo');

        $this->assertDatabaseMissing('equipment', ['serial_number' => 'SN-BADPHOTO-1']);
    }

    public function test_photo_over_2_mb_is_rejected(): void
    {
        Storage::fake('public');
        [$manager] = $this->manager();

        $this->actingAs($manager)->post(route('equipment.store'), [
            'name' => 'Big Photo',
            'serial_number' => 'SN-BIGPHOTO-1',
            'photo' => $this->png('big.png', 3 * 1024 * 1024),
        ])->assertSessionHasErrors('photo');
    }

    public function test_replacing_a_photo_removes_the_old_file(): void
    {
        Storage::fake('public');
        [$manager, $lab] = $this->manager();
        $item = Equipment::factory()->for($lab)->create(['photo_path' => 'equipment/old.png']);
        Storage::disk('public')->put('equipment/old.png', 'x');

        $this->actingAs($manager)->put(route('equipment.update', $item), [
            'name' => $item->name,
            'serial_number' => $item->serial_number,
            'photo' => $this->png('new.png'),
        ])->assertSessionHasNoErrors();

        Storage::disk('public')->assertMissing('equipment/old.png');
        Storage::disk('public')->assertExists($item->fresh()->photo_path);
    }

    // ---------- catalog search and filters ----------

    private function catalogNames(User $viewer, array $query): array
    {
        return $this->actingAs($viewer)
            ->get(route('equipment.index', $query))
            ->viewData('equipment')
            ->getCollection()
            ->pluck('name')
            ->all();
    }

    public function test_catalog_filters_use_real_records(): void
    {
        $deptA = Department::factory()->create();
        $deptB = Department::factory()->create();
        $labA = Lab::factory()->for($deptA)->create();
        $labB = Lab::factory()->for($deptB)->create();
        $tag = Tag::create(['name' => 'laser-test']);

        Equipment::factory()->for($labA)->create(['name' => 'Zulu Camera', 'serial_number' => 'SN-ZULU-1']);
        $yankee = Equipment::factory()->for($labB)->underRepair()->create(['name' => 'Yankee Printer', 'serial_number' => 'SN-YANKEE-1']);
        $yankee->tags()->attach($tag);

        $viewer = User::factory()->student()->create();

        $this->assertEqualsCanonicalizing(['Zulu Camera', 'Yankee Printer'], $this->catalogNames($viewer, []));
        $this->assertSame(['Zulu Camera'], $this->catalogNames($viewer, ['q' => 'Zulu']));
        $this->assertSame(['Yankee Printer'], $this->catalogNames($viewer, ['q' => 'SN-YANKEE']));
        $this->assertSame(['Yankee Printer'], $this->catalogNames($viewer, ['department_id' => $deptB->id]));
        $this->assertSame(['Zulu Camera'], $this->catalogNames($viewer, ['lab_id' => $labA->id]));
        $this->assertSame(['Yankee Printer'], $this->catalogNames($viewer, ['status' => 'under_repair']));
        $this->assertSame(['Zulu Camera'], $this->catalogNames($viewer, ['status' => 'available']));
        $this->assertSame(['Yankee Printer'], $this->catalogNames($viewer, ['tag' => $tag->id]));
        $this->assertSame([], $this->catalogNames($viewer, ['department_id' => $deptA->id, 'tag' => $tag->id]));
    }

    // ---------- safe delete ----------

    public function test_equipment_with_no_history_can_be_deleted_with_its_files(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        [$manager, $lab] = $this->manager();
        $tag = Tag::create(['name' => 'portable']);

        $item = Equipment::factory()->for($lab)->create(['photo_path' => 'equipment/photo.png']);
        $item->tags()->attach($tag);
        Storage::disk('public')->put('equipment/photo.png', 'x');
        Storage::disk('local')->put('documents/only-mine.pdf', 'x');
        Document::factory()->for($item)->create(['file_path' => 'documents/only-mine.pdf']);

        $this->actingAs($manager)
            ->delete(route('equipment.destroy', $item))
            ->assertRedirect(route('equipment.index'));

        $this->assertDatabaseMissing('equipment', ['id' => $item->id]);
        $this->assertDatabaseMissing('equipment_tag', ['equipment_id' => $item->id]);
        Storage::disk('public')->assertMissing('equipment/photo.png');
        Storage::disk('local')->assertMissing('documents/only-mine.pdf');
        $this->assertDatabaseHas('tags', ['id' => $tag->id]); // the tag itself survives
    }

    public function test_a_document_file_shared_with_another_item_is_kept(): void
    {
        Storage::fake('local');
        [$manager, $lab] = $this->manager();
        $item = Equipment::factory()->for($lab)->create();
        $other = Equipment::factory()->for($lab)->create();
        Storage::disk('local')->put('documents/shared.pdf', 'x');
        Document::factory()->for($item)->create(['file_path' => 'documents/shared.pdf']);
        Document::factory()->for($other)->create(['file_path' => 'documents/shared.pdf']);

        $this->actingAs($manager)->delete(route('equipment.destroy', $item));

        Storage::disk('local')->assertExists('documents/shared.pdf');
    }

    public function test_equipment_with_an_open_reservation_cannot_be_deleted(): void
    {
        [$manager, $lab] = $this->manager();

        foreach (['pending' => null, 'approved' => 'approved', 'checked_out' => 'activeCheckout'] as $label => $state) {
            $item = Equipment::factory()->for($lab)->create();
            $factory = Reservation::factory()->for($item);
            $state ? $factory->{$state}()->create() : $factory->create();

            $this->actingAs($manager)
                ->delete(route('equipment.destroy', $item))
                ->assertRedirect(route('equipment.show', $item))
                ->assertSessionHasErrors('delete');

            $this->assertDatabaseHas('equipment', ['id' => $item->id]);
        }
    }

    public function test_equipment_with_unresolved_maintenance_cannot_be_deleted(): void
    {
        [$manager, $lab] = $this->manager();
        $item = Equipment::factory()->for($lab)->create();
        MaintenanceLog::factory()->for($item)->unresolved()->create(['reported_by' => $manager->id]);

        $this->actingAs($manager)
            ->delete(route('equipment.destroy', $item))
            ->assertSessionHasErrors('delete');

        $this->assertDatabaseHas('equipment', ['id' => $item->id]);
    }

    public function test_equipment_with_past_history_is_protected_from_deletion(): void
    {
        [$manager, $lab] = $this->manager();

        $withReservation = Equipment::factory()->for($lab)->create();
        Reservation::factory()->for($withReservation)->returned()->create();

        $withMaintenance = Equipment::factory()->for($lab)->create();
        MaintenanceLog::factory()->for($withMaintenance)->create(['reported_by' => $manager->id]); // resolved

        foreach ([$withReservation, $withMaintenance] as $item) {
            $this->actingAs($manager)
                ->delete(route('equipment.destroy', $item))
                ->assertSessionHasErrors('delete');
            $this->assertDatabaseHas('equipment', ['id' => $item->id]);
        }
    }

    public function test_lab_manager_cannot_delete_another_labs_equipment(): void
    {
        [$manager] = $this->manager();
        $foreign = Equipment::factory()->create();

        $this->actingAs($manager)->delete(route('equipment.destroy', $foreign))->assertForbidden();
        $this->assertDatabaseHas('equipment', ['id' => $foreign->id]);
    }

    public function test_student_cannot_delete_equipment(): void
    {
        $student = User::factory()->student()->create();
        $item = Equipment::factory()->create();

        $this->actingAs($student)->delete(route('equipment.destroy', $item))->assertForbidden();
        $this->assertDatabaseHas('equipment', ['id' => $item->id]);
    }

    public function test_details_page_shows_why_delete_is_unavailable(): void
    {
        [$manager, $lab] = $this->manager();
        $item = Equipment::factory()->for($lab)->create();
        Reservation::factory()->for($item)->create();

        $this->actingAs($manager)
            ->get(route('equipment.show', $item))
            ->assertOk()
            ->assertSee('Delete unavailable');
    }
}
