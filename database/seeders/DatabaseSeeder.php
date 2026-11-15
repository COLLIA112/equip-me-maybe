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
use Illuminate\Support\Carbon;
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
        $items = collect();
        // Existing/legacy equipment counts toward the target and is never renamed or removed.
        // Deterministic serials identify only records owned by this demo seeder.
        $equipmentCount = Equipment::count();
        for ($i = 0; $i < 250; $i++) {
            $serial = sprintf('EMM-DEMO-%04d', $i + 1);
            $item = Equipment::where('serial_number', $serial)->first();
            $created = false;
            if (! $item && $equipmentCount < 250) {
                $item = Equipment::factory()->for($labs[$i % $labs->count()])->create(['serial_number' => $serial]);
                $equipmentCount++;
                $created = true;
            }
            if (! $item) {
                continue;
            }
            $items->push($item);
            if ($created) {
                $item->tags()->syncWithoutDetaching($tags->random(random_int(1, 3))->pluck('id')->all());
            }

            $documentTarget = $i < 150 ? 2 : 1;
            while ($item->documents()->count() < $documentTarget && Document::count() < 400) {
                Document::factory()->for($item)->create(['is_required' => ! $item->documents()->where('is_required', true)->exists()]);
            }
            $manager = $managers[$labs[$i % $labs->count()]->id];
            $this->seedReservations($item, $manager, $borrowers, $i);

            $maintenanceTarget = (($i >= 100 && $i < 115) ? 1 : 0) + (($i % 3 === 0 && $i < 195) ? 1 : 0);
            while ($item->maintenanceLogs()->count() < $maintenanceTarget && MaintenanceLog::count() < 80) {
                $unresolved = $i >= 100 && $i < 115 && ! $item->maintenanceLogs()->whereNull('resolved_at')->exists();
                MaintenanceLog::factory()->for($item)->create([
                    'reported_by' => $manager->id,
                    'reservation_id' => $item->reservations()->where('status', ReservationStatus::Returned)->value('id'),
                    'resolved_at' => $unresolved ? null : now()->subDay(),
                ]);
                if ($unresolved && $created) {
                    $item->update(['status' => EquipmentStatus::UnderRepair]);
                }
            }
        }

        if ($items->isEmpty()) {
            $items = Equipment::whereHas('documents', fn ($query) => $query->where('file_path', 'documents/sample.pdf'))->orderBy('id')->get();
        }

        // A legacy dataset can consume equipment slots without the expected related records.
        // Top up deficits only: never trim user-created data or overwrite edited rows.
        if ($items->isNotEmpty()) {
            $item = $items->first();
            while (Document::count() < 400) {
                Document::factory()->for($item)->create();
            }
            $manager = User::where('role', Role::LabManager)->where('lab_id', $item->lab_id)->first();
            if (! $manager) {
                $manager = $this->demoUser('legacy-manager-'.$item->lab_id, Role::LabManager,
                    Department::findOrFail($item->lab()->value('department_id')), $password, $item->lab()->firstOrFail());
            }
            $slot = 3;
            while (Reservation::count() < 600) {
                $this->historicalReservation($item, $manager, $borrowers[$slot % $borrowers->count()], $slot++);
            }
            while (MaintenanceLog::count() < 80) {
                MaintenanceLog::factory()->for($item)->create(['reported_by' => $manager->id]);
            }
        }
        $this->seedCompliance($borrowers, $items);
    }

    private function seedCompliance(Collection $borrowers, Collection $items): void
    {
        $documents = Document::whereIn('equipment_id', $items->pluck('id'))
            ->where('file_path', 'documents/sample.pdf')->orderBy('id')->pluck('id');
        $compliantBookings = Reservation::whereIn('equipment_id', $items->pluck('id'))
            ->whereIn('status', [ReservationStatus::Approved, ReservationStatus::CheckedOut, ReservationStatus::Returned])
            ->with(['equipment.documents' => fn ($query) => $query->where('is_required', true)])
            ->get()->groupBy('user_id');
        $requiredByBorrower = $compliantBookings->map(fn ($rows) => $rows->flatMap(fn ($reservation) => $reservation->equipment->documents->pluck('id'))->unique());
        $firstRequest = $compliantBookings->map(fn ($rows) => $rows->min('created_at'));

        foreach ($borrowers as $i => $borrower) {
            $existing = $borrower->documents()->get()->keyBy('id');
            $required = $requiredByBorrower[$borrower->id] ?? collect();
            $chosen = $existing->keys()->merge($required)->unique();
            for ($offset = 0; $chosen->count() < min(15, $documents->count()) && $offset < $documents->count(); $offset++) {
                $chosen->push($documents[($i * 17 + $offset) % $documents->count()]);
                $chosen = $chosen->unique();
            }
            foreach ($chosen as $offset => $id) {
                $pivot = $existing->get($id)?->pivot;
                if ($pivot && (! $required->contains($id) || ($pivot->downloaded_at && $pivot->acknowledged_at))) {
                    continue;
                }
                $downloadedAt = $pivot?->downloaded_at ?? (isset($firstRequest[$borrower->id]) ? Carbon::parse($firstRequest[$borrower->id])->subHours(2) : now()->subDays(30));
                $borrower->documents()->syncWithoutDetaching([$id => [
                    'downloaded_at' => $downloadedAt,
                    'acknowledged_at' => $pivot?->acknowledged_at ?? ($required->contains($id) || ($i + $offset) % 4 !== 0 ? Carbon::parse($downloadedAt)->addHour() : null),
                ]]);
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

    private function seedReservations(Equipment $item, User $manager, Collection $borrowers, int $index): void
    {
        $target = $index < 100 ? 3 : 2;
        foreach ([0, 1] as $slot) {
            if ($item->reservations()->count() >= $target || Reservation::count() >= 600) {
                break;
            }
            $days = $slot === 0 ? 20 : 5;
            $start = $item->created_at->copy()->subDays($days)->setTime(9, 0);
            if (! $item->reservations()->where('starts_at', $start)->exists()) {
                $this->historicalReservation($item, $manager, $borrowers[($index * 3 + $slot) % $borrowers->count()], $slot);
            }
        }
        if ($index < 100 && $item->reservations()->count() < $target && Reservation::count() < 600) {
            $borrower = $borrowers[($index * 3 + 2) % $borrowers->count()];
            if ($index < 20) {
                Reservation::factory()->for($item)->for($borrower, 'user')->activeCheckout()->create([
                    'approved_by' => $manager->id, 'approved_at' => now()->subMinutes(90),
                    'handed_over_by' => $manager->id, 'created_at' => now()->subHours(2),
                ]);
                if ($item->status === EquipmentStatus::Available) {
                    $item->update(['status' => EquipmentStatus::Reserved]);
                }
            } else {
                $start = $item->created_at->copy()->addDays(7)->setTime(9, 0);
                $status = $index % 2 === 0 ? ReservationStatus::Approved : ReservationStatus::Pending;
                Reservation::factory()->for($item)->for($borrower, 'user')->create([
                    'starts_at' => $start, 'ends_at' => $start->copy()->addHours(4), 'status' => $status,
                    'approved_by' => $status === ReservationStatus::Approved ? $manager->id : null,
                    'approved_at' => $status === ReservationStatus::Approved ? now() : null,
                ]);
            }
        }
    }

    private function historicalReservation(Equipment $item, User $manager, User $borrower, int $slot): void
    {
        $days = match ($slot) {
            0 => 20, 1 => 5, default => 20 + $slot * 7
        };
        $start = $item->created_at->copy()->subDays($days)->setTime(9, 0);
        $end = $start->copy()->addHours(4);
        $status = match (($item->id + $slot) % 4) {
            0, 1 => ReservationStatus::Returned,
            2 => ReservationStatus::Rejected,
            default => ReservationStatus::Cancelled,
        };
        Reservation::factory()->for($item)->for($borrower, 'user')->create([
            'starts_at' => $start, 'ends_at' => $end, 'status' => $status,
            'approved_by' => $status === ReservationStatus::Returned ? $manager->id : null,
            'handed_over_at' => $status === ReservationStatus::Returned ? $start : null,
            'returned_at' => $status === ReservationStatus::Returned ? $end : null,
            'created_at' => $start->copy()->subDay(),
            'approved_at' => $status === ReservationStatus::Returned ? $start->copy()->subHour() : null,
            'handed_over_by' => $status === ReservationStatus::Returned ? $manager->id : null,
            'returned_by' => $status === ReservationStatus::Returned ? $manager->id : null,
            'rejected_by' => $status === ReservationStatus::Rejected ? $manager->id : null,
            'rejected_at' => $status === ReservationStatus::Rejected ? $start->copy()->subHour() : null,
            'rejection_reason' => $status === ReservationStatus::Rejected ? 'Demo request rejected because the item was unavailable.' : null,
            'cancelled_by' => $status === ReservationStatus::Cancelled ? $borrower->id : null,
            'cancelled_at' => $status === ReservationStatus::Cancelled ? $start->copy()->subHour() : null,
        ]);
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
