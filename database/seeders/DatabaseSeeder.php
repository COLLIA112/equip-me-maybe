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
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $disk = Storage::disk('local');

        if (! $disk->exists('documents/sample.pdf')) {
            $disk->put('documents/sample.pdf', $this->samplePdf());
        }

        DB::transaction(function () {
            $this->seedDemoData();
        });
    }

    private function seedDemoData(): void
    {
        $labNames = [
            'Engineering' => ['Electronics Lab', 'Fabrication Lab', 'Robotics Lab', 'Materials Lab'],
            'Communications' => ['Broadcast Studio', 'Audio Lab', 'Photo Lab', 'Media Editing Suite'],
            'Fine Arts' => ['Printmaking Studio', 'Sculpture Workshop', 'Digital Art Lab', 'Ceramics Studio'],
        ];

        $departments = collect();
        $labs = collect();
        $managers = [];
        $borrowers = collect();
        $password = Hash::make('password');

        foreach ($labNames as $name => $names) {
            $department = Department::firstOrCreate(['name' => $name]);
            $departments->push($department);
            $number = $departments->count();

            $this->demoUser("head{$number}", Role::DepartmentHead, $department, $password, name: "Head {$name}");
            $this->demoUser("auditor{$number}", Role::SecurityAuditor, $department, $password);

            foreach ($names as $labName) {
                $lab = $department->labs()->firstOrCreate(['name' => $labName], [
                    'location' => 'Room '.(101 + $labs->count()),
                ]);
                $labs->push($lab);
                $managers[$lab->id] = $this->demoUser('manager'.$labs->count(), Role::LabManager, $department, $password, $lab);
            }
        }

        foreach ([Role::Faculty->value => 20, Role::Student->value => 82] as $role => $count) {
            for ($i = 0; $i < $count; $i++) {
                $borrowers->push($this->demoUser($role.($i + 1), Role::from($role), $departments[$i % 3], $password));
            }
        }

        $tagNames = ['fragile', 'outdoor', 'needs certification', 'battery powered', 'high voltage', 'optical', 'laser',
            'audio', 'video', 'radio frequency', '3D printing', 'drone', 'portable', 'studio only', 'calibration due'];
        $tags = collect($tagNames)->map(fn (string $name) => Tag::firstOrCreate(['name' => $name]));
        // The original demo seeder used random serials and this shared document path.
        $hasLegacyDemo = Document::where('file_path', 'documents/sample.pdf')
            ->whereHas('equipment', fn ($query) => $query->where('serial_number', 'not like', 'EMM-DEMO-%'))
            ->exists();

        if ($hasLegacyDemo) {
            return;
        }

        $documents = collect();

        for ($i = 0; $i < 250; $i++) {
            $lab = $labs[$i % $labs->count()];
            $serial = sprintf('EMM-DEMO-%04d', $i + 1);
            $item = Equipment::where('serial_number', $serial)->first();

            if (! $item) {
                $item = Equipment::factory()->for($lab)->create(['serial_number' => $serial]);
                $item->tags()->syncWithoutDetaching($tags->random(random_int(1, 3))->pluck('id')->all());
                Document::factory()->for($item)->required()->create();

                if ($i < 150) {
                    Document::factory()->for($item)->create();
                }

                $manager = $managers[$lab->id];
                $returned = $this->seedReservations($item, $manager, $borrowers, $i);

                if ($i >= 100 && $i < 115) {
                    MaintenanceLog::factory()->for($item)->unresolved()->create(['reported_by' => $manager->id]);
                    $item->update(['status' => EquipmentStatus::UnderRepair]);
                }

                if ($i % 3 === 0 && $i < 195) {
                    MaintenanceLog::factory()->for($item)->create([
                        'reported_by' => $manager->id,
                        'reservation_id' => $returned?->id,
                    ]);
                }
            }

            $documents = $documents->merge($item->documents()
                ->where('file_path', 'documents/sample.pdf')->orderBy('id')->pluck('id'));
        }

        foreach ($borrowers as $i => $borrower) {
            $rows = [];
            $existing = $borrower->documents()->pluck('documents.id')->flip();

            for ($offset = 0; $offset < min(15, $documents->count()); $offset++) {
                $id = $documents[($i * 17 + $offset) % $documents->count()];

                if (! $existing->has($id)) {
                    $downloadedAt = now()->subDays(7);
                    $rows[$id] = [
                        'downloaded_at' => $downloadedAt,
                        'acknowledged_at' => ($i + $offset) % 4 === 0 ? null : $downloadedAt->copy()->addHour(),
                    ];
                }
            }

            if ($rows !== []) {
                $borrower->documents()->syncWithoutDetaching($rows);
            }
        }
    }

    private function demoUser(string $login, Role $role, Department $department, string $password, ?Lab $lab = null, ?string $name = null): User
    {
        return User::firstOrCreate(['email' => "{$login}@equip.test"], [
            'name' => $name ?? fake()->name(),
            'password' => $password,
            'role' => $role,
            'department_id' => $department->id,
            'lab_id' => $lab?->id,
            'email_verified_at' => now(),
        ]);
    }

    private function seedReservations(Equipment $item, User $manager, Collection $borrowers, int $index): ?Reservation
    {
        $returned = null;

        foreach ([-20, -5] as $days) {
            $start = now()->addDays($days)->setTime(9, 0);
            $end = $start->copy()->addHours(4);
            $status = fake()->randomElement([ReservationStatus::Returned, ReservationStatus::Returned, ReservationStatus::Rejected, ReservationStatus::Cancelled]);
            $reservation = Reservation::factory()->for($item)->for($borrowers->random(), 'user')->create([
                'starts_at' => $start,
                'ends_at' => $end,
                'status' => $status,
                'approved_by' => $status === ReservationStatus::Returned ? $manager->id : null,
                'handed_over_at' => $status === ReservationStatus::Returned ? $start : null,
                'returned_at' => $status === ReservationStatus::Returned ? $end : null,
            ]);

            if ($status === ReservationStatus::Returned) {
                $returned = $reservation;
            }
        }

        if ($index < 20) {
            Reservation::factory()->for($item)->for($borrowers->random(), 'user')->activeCheckout()->create([
                'approved_by' => $manager->id,
            ]);
            $item->update(['status' => EquipmentStatus::Reserved]);
        } elseif ($index < 100) {
            $start = now()->addDays(7)->setTime(9, 0);
            $status = fake()->randomElement([ReservationStatus::Pending, ReservationStatus::Approved]);
            Reservation::factory()->for($item)->for($borrowers->random(), 'user')->create([
                'starts_at' => $start,
                'ends_at' => $start->copy()->addHours(4),
                'status' => $status,
                'approved_by' => $status === ReservationStatus::Approved ? $manager->id : null,
            ]);
        }

        return $returned;
    }

    private function samplePdf(): string
    {
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 144] /Contents 4 0 R >>',
            "<< /Length 0 >>\nstream\nendstream",
        ];
        $pdf = "%PDF-1.4\n";
        $xref = "0000000000 65535 f \n";

        foreach ($objects as $i => $object) {
            $xref .= sprintf("%010d 00000 n \n", strlen($pdf));
            $pdf .= ($i + 1)." 0 obj\n{$object}\nendobj\n";
        }

        return $pdf."xref\n0 5\n{$xref}trailer\n<< /Root 1 0 R /Size 5 >>\nstartxref\n".strlen($pdf)."\n%%EOF\n";
    }
}
