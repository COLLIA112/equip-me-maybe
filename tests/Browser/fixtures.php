<?php

use App\Enums\EquipmentStatus;
use App\Enums\ReservationStatus;
use App\Models\Document;
use App\Models\Equipment;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing')) {
    throw new RuntimeException('Browser fixtures require APP_ENV=testing and an isolated database.');
}

if (($argv[1] ?? null) === '--open-handover-window') {
    $reservation = Reservation::query()->where('status', ReservationStatus::Approved)
        ->whereHas('equipment', fn ($query) => $query->where('serial_number', 'like', 'QA-LIFE-%'))
        ->findOrFail((int) ($argv[2] ?? 0));
    $reservation->update(['starts_at' => now()->subMinute(), 'ends_at' => now()->addHour()]);

    exit;
}

$lifecycleBorrower = User::factory()->student()->create(['email' => 'lifecycle1@equip.test']);
$manager = User::whereEmail('manager1@equip.test')->firstOrFail();
$student = User::whereEmail('student1@equip.test')->firstOrFail();
$faculty = User::whereEmail('faculty1@equip.test')->firstOrFail();
$out = ['lab_id' => $manager->lab_id];
foreach (['cancel', 'reject', 'priority', 'active', 'overdue'] as $name) {
    $item = Equipment::factory()->create(['lab_id' => $manager->lab_id, 'name' => 'QA Browser '.ucfirst($name), 'serial_number' => 'QA-BROWSER-'.strtoupper($name)]);
    $isActive = in_array($name, ['active', 'overdue']);
    $start = $isActive ? now()->subHours(2) : now()->addDays(2)->setTime(10, 0);
    $end = $name === 'overdue' ? now()->subHour() : ($isActive ? now()->addHour() : $start->copy()->addHour());
    $borrower = $name === 'reject' ? $lifecycleBorrower : $student;
    $res = Reservation::factory()->for($item)->for($borrower, 'user')->create(['starts_at' => $start, 'ends_at' => $end, 'status' => $isActive ? ReservationStatus::CheckedOut : ReservationStatus::Pending, 'approved_by' => $isActive ? $manager->id : null, 'handed_over_at' => $isActive ? $start : null]);
    if ($isActive) {
        $item->update(['status' => EquipmentStatus::Reserved]);
    }
    $out[$name] = ['equipment' => $item->id, 'reservation' => $res->id, 'code' => $item->qr_code, 'start' => $start->format('Y-m-d\TH:i'), 'end' => $end->format('Y-m-d\TH:i')];
}

foreach (['markall', 'bulkready', 'bulkblocked', 'past'] as $name) {
    $item = Equipment::factory()->create(['lab_id' => $manager->lab_id, 'name' => 'QA Extras '.ucfirst($name), 'serial_number' => 'QA-EXTRA-'.strtoupper($name)]);
    $start = $name === 'past' ? now()->subDays(2)->setTime(9, 0) : now()->addDays(4)->setTime(10, 0);
    $end = $start->copy()->addHours(2);
    $res = Reservation::factory()->for($item)->for($student, 'user')->create(['starts_at' => $start, 'ends_at' => $end, 'status' => $name === 'past' ? ReservationStatus::Returned : ReservationStatus::Pending]);
    $docs = [];
    if (in_array($name, ['markall', 'bulkblocked'])) {
        for ($i = 1; $i <= ($name === 'markall' ? 2 : 1); $i++) {
            $d = Document::factory()->for($item)->required()->create(['title' => 'QA '.$name.' safety '.$i]);
            $docs[] = $d->id;
        }
    }
    $out[$name] = ['equipment' => $item->id, 'reservation' => $res->id, 'code' => $item->qr_code, 'documents' => $docs];
}
echo json_encode($out, JSON_PRETTY_PRINT);
