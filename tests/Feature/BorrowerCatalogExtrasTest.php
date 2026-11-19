<?php

namespace Tests\Feature;

use App\Enums\EquipmentStatus;
use App\Enums\Role;
use App\Models\Document;
use App\Models\Equipment;
use App\Models\LabClosure;
use App\Models\MaintenanceLog;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BorrowerCatalogExtrasTest extends TestCase
{
    use RefreshDatabase;

    public static function roles(): array
    {
        return [
            'student' => ['student', true, true], 'faculty' => ['faculty', true, true],
            'manager' => ['lab_manager', true, false], 'head' => ['department_head', true, false],
            'auditor' => ['security_auditor', false, false],
        ];
    }

    private function actor(string $role, Equipment $item): User
    {
        return User::factory()->create([
            'role' => Role::from($role), 'department_id' => $item->lab->department_id,
            'lab_id' => $role === 'lab_manager' ? $item->lab_id : null,
        ]);
    }

    #[DataProvider('roles')]
    public function test_availability_and_week_calendar_respect_each_role(string $role, bool $viewer, bool $borrower): void
    {
        $this->travelTo(now()->setDate(2026, 12, 2)->setTime(10, 0));
        $item = Equipment::factory()->create(['name' => 'Calendar camera']);
        $booking = Reservation::factory()->for($item)->approved()->create([
            'starts_at' => now()->subHour(), 'ends_at' => now()->addHours(2),
        ]);
        $user = $this->actor($role, $item);
        $this->actingAs($user);
        $catalog = $this->get(route('equipment.index'));
        $details = $this->get(route('equipment.show', [$item, 'week' => '2026-12-02']));
        if (! $viewer) {
            $catalog->assertForbidden();
            $details->assertForbidden();

            return;
        }
        $catalog->assertOk()->assertSee('Booked until Dec 2, 2026 12:00 PM');
        $details->assertOk()->assertSee('Weekly booking calendar')->assertSee('Week of November 30, 2026')
            ->assertSee('Previous week')->assertSee('Next week')->assertDontSee($booking->user->name)
            ->assertSee('Dec 2, 2026 9:00 AM')->assertSee('Dec 2, 2026 12:00 PM');
        if ($borrower) {
            $catalog->assertSee('Save to wishlist')->assertSee('My wishlist');
            $details->assertSee('Save to wishlist');
        } else {
            $catalog->assertDontSee('Save to wishlist')->assertDontSee('My wishlist');
            $details->assertDontSee('Save to wishlist');
        }
    }

    public function test_glance_uses_current_bookings_and_repairs_not_future_or_pending_windows(): void
    {
        $this->travelTo(now()->setDate(2026, 12, 2)->setTime(10, 0));
        $item = Equipment::factory()->create(['status' => EquipmentStatus::Reserved]);
        Reservation::factory()->for($item)->approved()->create(['starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour()]);
        Reservation::factory()->for($item)->create(['starts_at' => now()->subHour(), 'ends_at' => now()->addHour()]);
        $this->assertSame('Free now', Equipment::withAvailability()->findOrFail($item->id)->availabilityGlance()['label']);
        $active = Reservation::factory()->for($item)->checkedOut()->create(['starts_at' => now()->subHour(), 'ends_at' => now()->addHour()]);
        $this->assertSame('Booked until Dec 2, 2026 11:00 AM', Equipment::withAvailability()->findOrFail($item->id)->availabilityGlance()['label']);
        $log = MaintenanceLog::factory()->for($item)->unresolved()->create();
        $this->assertSame('Under repair', Equipment::withAvailability()->findOrFail($item->id)->availabilityGlance()['label']);
    }

    public function test_overdue_checkout_and_current_lab_closure_never_show_free_now(): void
    {
        $this->travelTo(now()->setDate(2026, 12, 2)->setTime(10, 0));
        $item = Equipment::factory()->create();
        $checkout = Reservation::factory()->for($item)->checkedOut()->create(['starts_at' => now()->subHours(3), 'ends_at' => now()->subHour()]);
        $this->assertSame('Checked out; overdue since Dec 2, 2026 9:00 AM', Equipment::withAvailability()->findOrFail($item->id)->availabilityGlance()['label']);
        $closure = LabClosure::create(['lab_id' => $item->lab_id, 'starts_at' => now()->subHour(), 'ends_at' => now()->addHours(3), 'reason' => 'Safety inspection', 'created_by' => User::factory()->create()->id]);
        $this->assertSame('Lab closed until Dec 2, 2026 1:00 PM', Equipment::withAvailability()->findOrFail($item->id)->availabilityGlance()['label']);
    }

    public function test_calendar_ignores_invalid_week_and_has_full_cross_day_dates(): void
    {
        $this->travelTo(now()->setDate(2026, 12, 2)->setTime(10, 0));
        $item = Equipment::factory()->create();
        Reservation::factory()->for($item)->approved()->create(['starts_at' => '2026-12-02 23:00:00', 'ends_at' => '2026-12-03 01:00:00']);
        $this->actingAs(User::factory()->student()->create());
        foreach ([['bad'], '2026-02-30', 'not-a-date', '9999-12-30'] as $week) {
            $this->get(route('equipment.show', [$item, 'week' => $week]))->assertOk()
                ->assertSee('Week of November 30, 2026')->assertSee('Dec 2, 2026 11:00 PM')->assertSee('Dec 3, 2026 1:00 AM');
        }
        $this->get(route('equipment.show', [$item, 'week' => '2026-12-07']))->assertOk()
            ->assertSee('Week of December 7, 2026')->assertSee('No bookings');
    }

    public function test_catalog_global_search_finds_equipment_qr_code(): void
    {
        $item = Equipment::factory()->create(['qr_code' => 'EQ-SEARCH123']);
        $this->actingAs(User::factory()->student()->create())->get(route('equipment.index', ['q' => 'SEARCH123']))
            ->assertOk()->assertSee($item->name)->assertSee('Free now');
    }

    #[DataProvider('roles')]
    public function test_mark_all_read_is_borrower_only_and_preserves_download_timestamps(string $role, bool $viewer, bool $borrower): void
    {
        $item = Equipment::factory()->create();
        $actor = $this->actor($role, $item);
        $documents = Document::factory()->count(2)->for($item)->required()->create();
        $downloaded = now()->subDay()->startOfSecond();
        foreach ($documents as $doc) {
            $actor->documents()->attach($doc, ['downloaded_at' => $downloaded]);
        }
        $response = $this->actingAs($actor)->post(route('equipment.documents.acknowledge-all', $item));
        if (! $borrower) {
            $response->assertForbidden();
            $this->assertSame(0, $actor->documents()->wherePivotNotNull('acknowledged_at')->count());

            return;
        }
        $response->assertSessionHasNoErrors()->assertSessionHas('status', 'Marked 2 required documents as read for '.$item->name.'.');
        foreach ($actor->documents()->get() as $doc) {
            $this->assertNotNull($doc->pivot->acknowledged_at);
            $this->assertTrue(Carbon::parse($doc->pivot->downloaded_at)->equalTo($downloaded));
        }
        $first = $actor->documents()->first()->pivot->acknowledged_at;
        $this->travel(10)->minutes();
        $this->post(route('equipment.documents.acknowledge-all', $item))->assertSessionHasNoErrors();
        $this->assertTrue(Carbon::parse($actor->documents()->first()->pivot->acknowledged_at)->equalTo($first));
    }

    public function test_mark_all_read_requires_every_required_download_atomically_and_ignores_other_users(): void
    {
        $item = Equipment::factory()->create();
        $student = User::factory()->student()->create();
        $first = Document::factory()->for($item)->required()->create(['title' => 'First PDF']);
        $missing = Document::factory()->for($item)->required()->create(['title' => 'Missing safety PDF']);
        $student->documents()->attach($first, ['downloaded_at' => now()]);
        $other = User::factory()->student()->create();
        $other->documents()->attach($missing, ['downloaded_at' => now()]);
        $this->actingAs($student)->post(route('equipment.documents.acknowledge-all', $item))
            ->assertSessionHasErrors(['documents' => 'Download every required PDF first: Missing safety PDF.']);
        $this->assertSame(0, $student->documents()->wherePivotNotNull('acknowledged_at')->count());
        $this->get(route('equipment.show', $item))->assertOk()->assertSee('Mark all as read')
            ->assertSee('Download every required PDF first: Missing safety PDF.');
    }

    public function test_mark_all_read_has_no_get_action_and_invalid_equipment_is_not_found(): void
    {
        $student = User::factory()->student()->create();
        $this->actingAs($student)->post(route('equipment.documents.acknowledge-all', 999999))->assertNotFound();
        $item = Equipment::factory()->create();
        $this->get(route('equipment.documents.acknowledge-all', $item))->assertStatus(405);
    }

    public function test_checklist_progress_counts_filtered_documents_across_all_pages(): void
    {
        $item = Equipment::factory()->create();
        $student = User::factory()->student()->create();
        Reservation::factory()->for($item)->for($student, 'user')->create();
        $documents = Document::factory()->count(12)->for($item)->required()->create();
        foreach ($documents->take(3) as $doc) {
            $student->documents()->attach($doc, ['downloaded_at' => now(), 'acknowledged_at' => now()]);
        }
        $this->actingAs($student)->get(route('documents.checklist'))->assertOk()->assertSee('3 of 12 matching documents acknowledged');
        $this->get(route('documents.checklist', ['acknowledged' => 'no']))->assertOk()->assertSee('0 of 9 matching documents acknowledged');
        $this->get(route('documents.checklist', ['acknowledged' => 'yes']))->assertOk()->assertSee('3 of 3 matching documents acknowledged');
    }

    public function test_catalog_availability_does_not_add_queries_per_equipment(): void
    {
        $item = Equipment::factory()->create();
        $student = User::factory()->student()->create();
        $this->actingAs($student);
        DB::enableQueryLog();
        $this->get(route('equipment.index'))->assertOk();
        $initial = count(DB::getQueryLog());
        DB::disableQueryLog();
        Equipment::factory()->count(6)->for($item->lab)->create();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('equipment.index'))->assertOk();
        $this->assertLessThanOrEqual($initial + 1, count(DB::getQueryLog()));
        DB::disableQueryLog();
    }

    public function test_mark_all_read_only_changes_required_documents_for_this_equipment(): void
    {
        $item = Equipment::factory()->create();
        $student = User::factory()->student()->create();
        $required = Document::factory()->for($item)->required()->create();
        $optional = Document::factory()->for($item)->create(['is_required' => false]);
        $elsewhere = Document::factory()->required()->create();
        foreach ([$required, $optional, $elsewhere] as $doc) {
            $student->documents()->attach($doc, ['downloaded_at' => now()]);
        }
        $this->actingAs($student)->post(route('equipment.documents.acknowledge-all', $item))->assertSessionHasNoErrors();
        $this->assertNotNull($student->documents()->find($required->id)->pivot->acknowledged_at);
        $this->assertNull($student->documents()->find($optional->id)->pivot->acknowledged_at);
        $this->assertNull($student->documents()->find($elsewhere->id)->pivot->acknowledged_at);
    }

    public function test_numbered_model_groups_keep_unit_availability_and_wishlist_actions_in_the_drill_down(): void
    {
        $item = Equipment::factory()->create(['name' => 'Studio Camera #1']);
        $repair = Equipment::factory()->for($item->lab)->underRepair()->create(['name' => 'Studio Camera #2']);
        $student = User::factory()->student()->create();
        $student->wishlist()->attach($item);

        $this->actingAs($student)->get(route('equipment.index'))
            ->assertOk()->assertSee('Quantity: 2')->assertSee('Available: 1')
            ->assertSee(route('equipment.index', ['model' => 'Studio Camera']));
        $this->get(route('equipment.index', ['model' => 'Studio Camera']))
            ->assertOk()->assertSee($item->name)->assertSee($repair->name)
            ->assertSee('Free now')->assertSee('Under repair')
            ->assertSee('Remove from wishlist')->assertSee('Save to wishlist');
    }

    public function test_catalog_and_details_show_existing_wishlist_remove_actions(): void
    {
        $item = Equipment::factory()->create();
        $student = User::factory()->student()->create();
        $student->wishlist()->attach($item);
        $this->actingAs($student)->get(route('equipment.index'))->assertOk()->assertSee('Remove from wishlist')->assertDontSee('Save to wishlist');
        $this->get(route('equipment.show', $item))->assertOk()->assertSee('Remove from wishlist')->assertDontSee('Save to wishlist');
    }
}
