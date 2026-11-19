<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Models\Document;
use App\Models\Equipment;
use App\Models\Lab;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SeederRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_recovers_missing_related_demo_records_without_resetting_edits(): void
    {
        Storage::fake('local');
        $this->seed();
        $item = Equipment::where('serial_number', 'EMM-DEMO-0001')->sole();
        $item->update(['name' => 'Keep my equipment edit']);
        $item->documents()->first()->delete();
        $item->reservations()->oldest('starts_at')->first()->delete();
        DB::table('maintenance_logs')->orderBy('id')->limit(1)->delete();
        $this->seed();
        $this->assertSame('Keep my equipment edit', $item->fresh()->name);
        foreach (['equipment' => 250, 'documents' => 400, 'reservations' => 600, 'maintenance_logs' => 80] as $table => $count) {
            $this->assertDatabaseCount($table, $count);
        }
        $this->seed();
        $this->assertDatabaseCount('documents', 400);
        $this->assertDatabaseCount('reservations', 600);
    }

    public function test_legacy_shared_pdf_records_do_not_prevent_demo_top_up(): void
    {
        Storage::fake('local');
        $legacy = Equipment::factory()->create(['name' => 'Preserve legacy camera', 'serial_number' => 'LEGACY-CAMERA-1']);
        Document::factory()->for($legacy)->required()->create();
        $this->seed();
        $this->assertSame('Preserve legacy camera', $legacy->fresh()->name);
        $this->assertDatabaseCount('equipment', 250);
        $this->assertDatabaseCount('documents', 400);
        $this->assertDatabaseCount('reservations', 600);
        $this->assertDatabaseCount('maintenance_logs', 80);
        $this->seed();
        $this->assertDatabaseCount('equipment', 250);
    }

    public function test_seeded_approved_checked_out_and_returned_bookings_have_compliant_documents(): void
    {
        Storage::fake('local');
        $this->seed();
        $reservations = Reservation::whereIn('status', [ReservationStatus::Approved, ReservationStatus::CheckedOut, ReservationStatus::Returned])
            ->with(['equipment.documents', 'user.documents'])->get();
        $this->assertNotEmpty($reservations);
        foreach ($reservations as $reservation) {
            foreach ($reservation->equipment->documents->where('is_required', true) as $document) {
                $pivot = $reservation->user->documents->firstWhere('id', $document->id)?->pivot;
                $this->assertNotNull($pivot?->downloaded_at, "Reservation {$reservation->id} requires a download");
                $this->assertNotNull($pivot?->acknowledged_at, "Reservation {$reservation->id} requires acknowledgement");
            }
        }
        $this->assertDatabaseCount('document_user', 1530);
    }

    public function test_new_demo_reservations_record_ordered_timeline_actors_and_timestamps(): void
    {
        Storage::fake('local');
        $this->seed();
        foreach (Reservation::all() as $reservation) {
            if (in_array($reservation->status, [ReservationStatus::Approved, ReservationStatus::CheckedOut, ReservationStatus::Returned], true)) {
                $this->assertNotNull($reservation->approved_at);
                $this->assertNotNull($reservation->approved_by);
                $this->assertTrue($reservation->created_at->lessThanOrEqualTo($reservation->approved_at));
            }
            if (in_array($reservation->status, [ReservationStatus::CheckedOut, ReservationStatus::Returned], true)) {
                $this->assertNotNull($reservation->handed_over_by);
                $this->assertTrue($reservation->approved_at->lessThanOrEqualTo($reservation->handed_over_at));
            }
            if ($reservation->status === ReservationStatus::Returned) {
                $this->assertNotNull($reservation->returned_by);
                $this->assertTrue($reservation->handed_over_at->lessThanOrEqualTo($reservation->returned_at));
            }
            if ($reservation->status === ReservationStatus::Rejected) {
                $this->assertNotNull($reservation->rejected_by);
                $this->assertNotNull($reservation->rejected_at);
            }
            if ($reservation->status === ReservationStatus::Cancelled) {
                $this->assertSame($reservation->user_id, $reservation->cancelled_by);
                $this->assertNotNull($reservation->cancelled_at);
            }
        }
    }

    public function test_a_full_legacy_equipment_catalog_also_receives_missing_related_demo_data(): void
    {
        Storage::fake('local');
        $lab = Lab::factory()->create();
        $items = Equipment::factory()->count(250)->for($lab)->create();
        foreach ($items as $item) {
            Document::factory()->for($item)->required()->create();
        }
        $this->seed();
        foreach (['equipment' => 250, 'documents' => 400, 'reservations' => 600, 'maintenance_logs' => 80] as $table => $count) {
            $this->assertDatabaseCount($table, $count);
        }
        $this->seed();
        $this->assertDatabaseCount('reservations', 600);
        foreach (Reservation::where('status', ReservationStatus::Returned)->with(['user.documents', 'equipment.documents'])->get() as $reservation) {
            foreach ($reservation->equipment->documents->where('is_required', true) as $document) {
                $acknowledged = $reservation->user->documents->firstWhere('id', $document->id)?->pivot?->acknowledged_at;
                $this->assertNotNull($acknowledged);
                $this->assertTrue(Carbon::parse($acknowledged)->lessThanOrEqualTo($reservation->approved_at));
            }
        }
    }
}
