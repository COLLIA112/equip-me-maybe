<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Models\Document;
use App\Models\Equipment;
use App\Models\Lab;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentComplianceTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private User $borrower;
    private Equipment $item;
    private Document $document;

    protected function setUp(): void
    {
        parent::setUp();

        $lab = Lab::factory()->create();
        $this->manager = User::factory()->labManager($lab)->create();
        $this->borrower = User::factory()->student()->create();
        $this->item = Equipment::factory()->for($lab)->create();
        $this->document = Document::factory()->for($this->item)->required()->create(['title' => 'Safety certificate']);
    }

    private function acknowledge(): void
    {
        $this->borrower->documents()->attach($this->document->id, [
            'downloaded_at' => now(),
            'acknowledged_at' => now(),
        ]);
    }

    public function test_approval_is_blocked_until_required_documents_are_acknowledged(): void
    {
        $reservation = Reservation::factory()->for($this->item)->for($this->borrower, 'user')->create();

        $this->actingAs($this->manager)
            ->patch(route('reservations.approve', $reservation))
            ->assertSessionHasErrors('compliance');

        $this->assertSame(ReservationStatus::Pending, $reservation->fresh()->status);

        $this->acknowledge();

        $this->actingAs($this->manager)
            ->patch(route('reservations.approve', $reservation))
            ->assertSessionHasNoErrors();

        $this->assertSame(ReservationStatus::Approved, $reservation->fresh()->status);
    }

    public function test_handover_is_blocked_until_required_documents_are_acknowledged(): void
    {
        $reservation = Reservation::factory()->for($this->item)->for($this->borrower, 'user')->approved()->create();

        $this->actingAs($this->manager)
            ->patch(route('reservations.handover', $reservation))
            ->assertSessionHasErrors('compliance');

        $this->assertSame(ReservationStatus::Approved, $reservation->fresh()->status);

        $this->acknowledge();

        $this->actingAs($this->manager)->patch(route('reservations.handover', $reservation));

        $this->assertSame(ReservationStatus::CheckedOut, $reservation->fresh()->status);
    }

    public function test_download_is_recorded_and_acknowledge_needs_a_download_first(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put($this->document->file_path, '%PDF-1.4');

        $this->actingAs($this->borrower)
            ->post(route('documents.acknowledge', $this->document))
            ->assertSessionHasErrors('document');

        $this->actingAs($this->borrower)->get(route('documents.download', $this->document))->assertOk();

        $pivot = $this->borrower->documents()->firstOrFail()->pivot;
        $this->assertNotNull($pivot->downloaded_at);
        $this->assertNull($pivot->acknowledged_at);

        $this->actingAs($this->borrower)
            ->post(route('documents.acknowledge', $this->document))
            ->assertSessionHasNoErrors();

        $this->assertNotNull($this->borrower->documents()->firstOrFail()->pivot->acknowledged_at);
    }
}
