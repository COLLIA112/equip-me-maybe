<?php

namespace Tests\Feature;

use App\Enums\EquipmentStatus;
use App\Enums\Role;
use App\Models\Document;
use App\Models\Equipment;
use App\Models\Lab;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EquipmentManagerToolsTest extends TestCase
{
    use RefreshDatabase;

    public static function roles(): array
    {
        return array_map(fn ($role) => [$role], Role::cases());
    }

    private function actor(Role $role, Lab $lab): User
    {
        return User::factory()->create(['role' => $role, 'department_id' => $lab->department_id, 'lab_id' => $role === Role::LabManager ? $lab->id : null]);
    }

    #[DataProvider('roles')]
    public function test_tools_and_bulk_labels_are_available_only_to_managers_and_heads(Role $role): void
    {
        $lab = Lab::factory()->create();
        $item = Equipment::factory()->for($lab)->create(['name' => 'Scoped camera']);
        $this->actingAs($this->actor($role, $lab));
        if (in_array($role, [Role::LabManager, Role::DepartmentHead], true)) {
            $this->get(route('equipment.tools.index'))->assertOk()->assertSee($item->name);
            $this->get(route('equipment.tools.labels'))->assertOk()->assertSee('QR code for '.$item->qr_code)->assertSee('Print labels');
            $this->get(route('equipment.tools.export'))->assertDownload('equipment-export.csv');
            $this->get(route('equipment.tools.template'))->assertDownload('equipment-import-template.csv');
        } else {
            foreach (['index', 'labels', 'export', 'template'] as $action) {
                $this->get(route('equipment.tools.'.$action))->assertForbidden();
            }
        }
    }

    #[DataProvider('roles')]
    public function test_only_the_own_lab_manager_can_clone_without_history_or_shared_files(Role $role): void
    {
        $lab = Lab::factory()->create();
        $source = Equipment::factory()->for($lab)->underRepair()->create(['photo_path' => 'equipment/original.png']);
        Document::factory()->for($source)->required()->create();
        $tag = Tag::factory()->create();
        $source->tags()->attach($tag);
        $this->actingAs($this->actor($role, $lab));
        $payload = ['name' => 'Cloned equipment', 'serial_number' => 'CLONE-NEW-001', 'lab_id' => 999, 'status' => 'under_repair', 'qr_code' => $source->qr_code, 'photo_path' => $source->photo_path];
        $response = $this->post(route('equipment.tools.clone', $source), $payload);
        if ($role === Role::LabManager) {
            $response->assertSessionHasNoErrors();
            $copy = Equipment::whereSerialNumber('CLONE-NEW-001')->sole();
            $response->assertRedirect(route('equipment.show', $copy));
            $this->assertSame($lab->id, $copy->lab_id);
            $this->assertSame(EquipmentStatus::Available, $copy->status);
            $this->assertNotSame($source->qr_code, $copy->qr_code);
            $this->assertNull($copy->photo_path);
            $this->assertSame([$tag->id], $copy->tags()->pluck('tags.id')->all());
            $this->assertSame(0, $copy->documents()->count());
            $this->assertSame(0, $copy->reservations()->count());
            $this->assertSame(0, $copy->maintenanceLogs()->count());
        } else {
            $response->assertForbidden();
            $this->assertDatabaseCount('equipment', 1);
        }
    }

    #[DataProvider('roles')]
    public function test_csv_import_is_manager_only_and_uses_the_managers_lab(Role $role): void
    {
        $lab = Lab::factory()->create();
        $tag = Tag::factory()->create(['name' => 'fragile']);
        $this->actingAs($this->actor($role, $lab));
        $file = UploadedFile::fake()->createWithContent('equipment.csv', "name,serial_number,tags\nCamera,CSV-001,fragile\nTripod,CSV-002,\n");
        $response = $this->post(route('equipment.tools.import'), ['file' => $file, 'lab_id' => 999, 'status' => 'reserved']);
        if ($role === Role::LabManager) {
            $response->assertSessionHasNoErrors()->assertSessionHas('status', '2 equipment items imported into your laboratory.');
            $this->assertDatabaseCount('equipment', 2);
            $item = Equipment::whereSerialNumber('CSV-001')->sole();
            $this->assertSame($lab->id, $item->lab_id);
            $this->assertSame(EquipmentStatus::Available, $item->status);
            $this->assertSame([$tag->id], $item->tags()->pluck('tags.id')->all());
        } else {
            $response->assertForbidden();
            $this->assertDatabaseCount('equipment', 0);
        }
    }

    public function test_manager_cannot_clone_or_download_another_labs_equipment(): void
    {
        $own = Equipment::factory()->create();
        $foreign = Equipment::factory()->create(['name' => 'Foreign secret camera']);
        $manager = User::factory()->labManager($own->lab)->create();
        $this->actingAs($manager)->post(route('equipment.tools.clone', $foreign), ['name' => 'Attack', 'serial_number' => 'ATTACK'])->assertForbidden();
        $this->get(route('equipment.tools.labels'))->assertSee($own->name)->assertDontSee($foreign->name);
        $response = $this->get(route('equipment.tools.export'));
        $this->assertStringContainsString($own->serial_number, $response->streamedContent());
        $this->assertStringNotContainsString($foreign->serial_number, $response->streamedContent());
        foreach (['index', 'labels', 'export'] as $action) {
            $this->get(route('equipment.tools.'.$action, ['lab_id' => $foreign->lab_id]))->assertForbidden();
        }
    }

    public function test_head_bulk_outputs_include_only_their_department(): void
    {
        $own = Equipment::factory()->create();
        $secondLab = Lab::factory()->for($own->lab->department)->create();
        $second = Equipment::factory()->for($secondLab)->create();
        $foreign = Equipment::factory()->create(['name' => 'Foreign hidden equipment']);
        $head = User::factory()->departmentHead($own->lab->department)->create();
        $this->actingAs($head)->get(route('equipment.tools.labels'))->assertOk()->assertSee($own->name)->assertSee($second->name)->assertDontSee($foreign->name);
        $this->get(route('equipment.tools.labels', ['lab_id' => $secondLab->id]))->assertOk()->assertSee($second->name)->assertDontSee($own->name);
        $this->get(route('equipment.tools.export', ['lab_id' => $foreign->lab_id]))->assertForbidden();
        $this->get(route('equipment.tools.index'))->assertDontSee('Validate and import CSV')->assertDontSee('Clone equipment');
    }

    public function test_clone_validates_new_name_and_unique_serial(): void
    {
        $item = Equipment::factory()->create();
        $this->actingAs(User::factory()->labManager($item->lab)->create());
        $this->post(route('equipment.tools.clone', $item), ['name' => '', 'serial_number' => $item->serial_number])->assertSessionHasErrors(['name', 'serial_number']);
        $this->post(route('equipment.tools.clone', $item), ['name' => str_repeat('x', 151), 'serial_number' => str_repeat('x', 101)])->assertSessionHasErrors(['name', 'serial_number']);
        $this->assertDatabaseCount('equipment', 1);
    }

    public static function invalidCsv(): array
    {
        return [
            'missing headers' => ["Camera,ABC\n"],
            'unknown column cannot set lab' => ["name,serial_number,lab_id\nCamera,NEW,999\n"],
            'duplicate header' => ["name,serial_number,name\nCamera,NEW,Attack\n"],
            'bad utf8' => ["name,serial_number\n\xFF,NEW\n"],
            'null byte' => ["name,serial_number\nCam\x00era,NEW\n"],
            'empty file' => [''],
            'header only' => ["name,serial_number\n"],
            'missing field' => ["name,serial_number\nGood,GOOD-001\nCamera,\n"],
            'duplicate serial in file' => ["name,serial_number\nGood,DUP-001\nCamera,DUP-001\n"],
            'unknown tag' => ["name,serial_number,tags\nGood,GOOD-001,\nCamera,BAD-001,unknown-tag\n"],
            'bad column count' => ["name,serial_number\nGood,GOOD-001\nCamera,BAD-001,extra\n"],
            'long name' => ["name,serial_number\n".str_repeat('a', 151).",LONG-NAME\n"],
            '501 rows' => ["name,serial_number\n".implode('', array_map(fn ($i) => "Camera {$i},LIMIT-{$i}\n", range(1, 501)))],
        ];
    }

    #[DataProvider('invalidCsv')]
    public function test_invalid_csv_reports_errors_and_imports_no_partial_rows(string $content): void
    {
        $manager = User::factory()->labManager()->create();
        $this->actingAs($manager)->post(route('equipment.tools.import'), ['file' => UploadedFile::fake()->createWithContent('equipment.csv', $content)])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('equipment', 0);
    }

    public function test_csv_rejects_existing_serial_oversize_and_wrong_upload_type(): void
    {
        $item = Equipment::factory()->create(['serial_number' => 'EXISTING-001']);
        $manager = User::factory()->labManager($item->lab)->create();
        $this->actingAs($manager)->post(route('equipment.tools.import'), ['file' => UploadedFile::fake()->createWithContent('equipment.csv', "name,serial_number\nNew,NEW-001\nDuplicate,EXISTING-001\n")])->assertSessionHasErrors('file');
        $this->post(route('equipment.tools.import'), ['file' => UploadedFile::fake()->create('equipment.csv', 2049, 'text/csv')])->assertSessionHasErrors('file');
        $this->post(route('equipment.tools.import'), ['file' => UploadedFile::fake()->image('camera.png')])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('equipment', 1);
    }

    public function test_csv_accepts_bom_reordered_headers_and_quoted_commas(): void
    {
        $manager = User::factory()->labManager()->create();
        $this->actingAs($manager)->post(route('equipment.tools.import'), ['file' => UploadedFile::fake()->createWithContent('equipment.csv', "\xEF\xBB\xBFserial_number,name\r\nBOM-001,\"Camera, video\"\r\n")])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('equipment', ['serial_number' => 'BOM-001', 'name' => 'Camera, video', 'lab_id' => $manager->lab_id]);
    }

    public function test_csv_export_neutralizes_spreadsheet_formulas_in_all_cells(): void
    {
        $lab = Lab::factory()->create();
        $item = Equipment::factory()->for($lab)->create(['name' => '=HYPERLINK("https://example.test")', 'serial_number' => '+FORMULA-SERIAL']);
        $tag = Tag::factory()->create(['name' => '@FORMULA-TAG']);
        $item->tags()->attach($tag);
        $this->actingAs(User::factory()->labManager($lab)->create());
        $response = $this->get(route('equipment.tools.export'))->assertDownload();
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $response->streamedContent());
        rewind($stream);
        fgetcsv($stream, null, ',', '"', '');
        $cells = fgetcsv($stream, null, ',', '"', '');
        fclose($stream);
        $this->assertSame("'".$item->name, $cells[0]);
        $this->assertSame("'".$item->serial_number, $cells[1]);
        $this->assertSame("'".$tag->name, $cells[2]);
    }
}
