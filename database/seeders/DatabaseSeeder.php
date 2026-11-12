<?php

namespace Database\Seeders;

use App\Enums\EquipmentStatus;
use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Models\Department;
use App\Models\Document;
use App\Models\Equipment;
use App\Models\Lab;
use App\Models\MaintenanceLog;
use App\Models\Reservation;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Target: 3 + 12 + 120 + 15 + 250 + 400 + 600 + 80 = 1,480 model rows (plus ~1,500 pivot rows).
 * Demo logins (password: "password"): head1@, manager1@, student1@, faculty1@, auditor1@ equip.test
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // One placeholder PDF so seeded documents can actually be downloaded.
        Storage::disk('local')->put('documents/sample.pdf', $this->samplePdf());

        // ---- 3 departments, 12 labs ----
        $labNames = [
            'Engineering' => ['Electronics Lab', 'Fabrication Lab', 'Robotics Lab', 'Materials Lab'],
            'Communications' => ['Broadcast Studio', 'Audio Lab', 'Photo Lab', 'Media Editing Suite'],
            'Fine Arts' => ['Printmaking Studio', 'Sculpture Workshop', 'Digital Art Lab', 'Ceramics Studio'],
        ];

        $departments = collect();
        $labs = collect();
        foreach ($labNames as $deptName => $names) {
            $department = Department::factory()
                ->has(Lab::factory()->count(4)->sequence(...array_map(fn ($n) => ['name' => $n], $names)))
                ->create(['name' => $deptName]);
            $departments->push($department);
            $labs = $labs->merge($department->labs);
        }

        // ---- 120 users: 3 heads, 12 managers, 3 auditors, 20 faculty, 82 students ----
        foreach ($departments as $i => $department) {
            User::factory()->departmentHead($department)->create(['name' => "Head {$department->name}", 'email' => 'head'.($i + 1).'@equip.test']);
            User::factory()->securityAuditor()->create(['department_id' => $department->id, 'email' => 'auditor'.($i + 1).'@equip.test']);
        }

        $managers = [];
        foreach ($labs->values() as $i => $lab) {
            $managers[$lab->id] = User::factory()->labManager($lab)->create(['email' => 'manager'.($i + 1).'@equip.test']);
        }

        User::factory()->count(20)->faculty()
            ->sequence(fn (Sequence $s) => ['email' => 'faculty'.($s->index + 1).'@equip.test', 'department_id' => $departments->random()->id])
            ->create();

        User::factory()->count(82)->student()
            ->sequence(fn (Sequence $s) => ['email' => 'student'.($s->index + 1).'@equip.test', 'department_id' => $departments->random()->id])
            ->create();

        $borrowers = User::whereIn('role', [Role::Student->value, Role::Faculty->value])->get();

        // ---- 15 tags ----
        $tagNames = ['fragile', 'outdoor', 'needs certification', 'battery powered', 'high voltage', 'optical', 'laser',
            'audio', 'video', 'radio frequency', '3D printing', 'drone', 'portable', 'studio only', 'calibration due'];
        $tags = Tag::factory()->count(15)->sequence(...array_map(fn ($n) => ['name' => $n], $tagNames))->create();

        // ---- 250 equipment, spread evenly over the 12 labs, each with 1 to 3 tags ----
        $labIds = $labs->pluck('id')->values();
        $equipment = Equipment::factory()->count(250)
            ->sequence(fn (Sequence $s) => ['lab_id' => $labIds[$s->index % 12]])
            ->afterCreating(fn (Equipment $e) => $e->tags()->attach($tags->random(random_int(1, 3))->pluck('id')))
            ->create()
            ->values();

        // ---- 400 documents: 150 items get 2 (first one required), 100 items get 1 (required) ----
        foreach ($equipment as $i => $item) {
            Document::factory()->for($item)->required()->create();
            if ($i < 150) {
                Document::factory()->for($item)->create();
            }
        }
        $documents = Document::all();

        // ---- document_user pivot: roughly 1,500 download and acknowledgement rows ----
        foreach ($borrowers as $borrower) {
            $rows = $documents->random(15)->mapWithKeys(fn ($d) => [$d->id => [
                'downloaded_at' => now()->subDays(random_int(1, 20)),
                'acknowledged_at' => random_int(1, 10) > 3 ? now()->subDays(random_int(0, 10)) : null,
            ]]);
            $borrower->documents()->attach($rows->all());
        }

        // ---- 600 reservations: 3 non overlapping time slots per item (past, past, future) ----
        $slotDays = [-20, -5, 7];
        for ($i = 0; $i < 600; $i++) {
            $item = $equipment[$i % 250];
            $slot = intdiv($i, 250);
            $start = now()->addDays($slotDays[$slot])->setTime(9, 0);
            $factory = Reservation::factory()->for($item)->for($borrowers->random(), 'user');

            if ($i >= 500 && $i < 520) {
                // 20 items are checked out right now: perfect for demoing the checkpoint API.
                $factory->activeCheckout()->create(['approved_by' => $managers[$item->lab_id]->id]);
                $item->update(['status' => EquipmentStatus::Reserved]);
                continue;
            }

            $status = match ($slot) {
                0, 1 => fake()->randomElement([ReservationStatus::Returned, ReservationStatus::Returned, ReservationStatus::Returned, ReservationStatus::Rejected, ReservationStatus::Cancelled]),
                default => fake()->randomElement([ReservationStatus::Pending, ReservationStatus::Approved]),
            };

            $factory->state([
                'starts_at' => $start,
                'ends_at' => $start->copy()->addHours(4),
                'status' => $status,
                'approved_by' => in_array($status, [ReservationStatus::Returned, ReservationStatus::Approved], true) ? $managers[$item->lab_id]->id : null,
                'handed_over_at' => $status === ReservationStatus::Returned ? $start : null,
                'returned_at' => $status === ReservationStatus::Returned ? $start->copy()->addHours(4) : null,
            ])->create();
        }

        // ---- 80 maintenance logs: 15 still open (item under repair), 65 resolved ----
        $returned = Reservation::where('status', ReservationStatus::Returned->value)->get()->keyBy('equipment_id');

        foreach ($equipment->slice(100, 15) as $item) {
            MaintenanceLog::factory()->unresolved()->create([
                'equipment_id' => $item->id,
                'reported_by' => $managers[$item->lab_id]->id,
            ]);
            $item->update(['status' => EquipmentStatus::UnderRepair]);
        }

        for ($i = 0; $i < 65; $i++) {
            $item = $equipment[($i * 3) % 250];
            MaintenanceLog::factory()->create([
                'equipment_id' => $item->id,
                'reported_by' => $managers[$item->lab_id]->id,
                'reservation_id' => $returned->get($item->id)?->id,
            ]);
        }
    }

    private function samplePdf(): string
    {
        return "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
            ."3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 300 144]>>endobj\n"
            ."trailer<</Root 1 0 R/Size 4>>\n%%EOF";
    }
}
