<?php

namespace Tests\Feature;

use App\Enums\EquipmentStatus;
use App\Models\Equipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_rerunning_the_demo_seeder_does_not_duplicate_or_reset_data(): void
    {
        Storage::fake('local');
        $this->seed();
        $counts = [
            'departments' => 3, 'labs' => 12, 'users' => 120, 'tags' => 15,
            'equipment' => 250, 'documents' => 400, 'reservations' => 600,
            'maintenance_logs' => 80, 'document_user' => 1530,
        ];
        $tags = DB::table('equipment_tag')->count();
        $item = Equipment::where('serial_number', 'EMM-DEMO-0001')->firstOrFail();
        $item->update(['status' => EquipmentStatus::UnderRepair]);
        $head = User::whereEmail('head1@equip.test')->firstOrFail();
        $head->update(['name' => 'Edited name']);

        $this->seed();

        foreach ($counts + ['equipment_tag' => $tags] as $table => $count) {
            $this->assertDatabaseCount($table, $count);
        }
        $this->assertSame('Edited name', $head->fresh()->name);
        $this->assertSame(EquipmentStatus::UnderRepair, $item->fresh()->status);
        Storage::disk('local')->assertExists('documents/sample.pdf');
    }
}
