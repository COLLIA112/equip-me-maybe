<?php

namespace Tests\Feature;

use App\Enums\EquipmentStatus;
use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Models\Document;
use App\Models\Equipment;
use App\Models\Lab;
use App\Models\LabClosure;
use App\Models\MaintenanceLog;
use App\Models\Reservation;
use App\Models\Tag;
use App\Models\User;
use App\Services\AvailabilityNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuditRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_deletion_blocks_open_work_and_preserves_repair_history(): void
    {
        $checkout = Reservation::factory()->activeCheckout()->create();
        $borrower = $checkout->user;
        $checkout->equipment->update(['status' => EquipmentStatus::Reserved]);
        $this->actingAs($borrower)->from('/profile')->delete('/profile', ['password' => 'password'])
            ->assertSessionHasErrorsIn('userDeletion', 'password');
        $this->assertAuthenticatedAs($borrower);
        $this->assertDatabaseHas('reservations', ['id' => $checkout->id, 'status' => 'checked_out']);

        $item = Equipment::factory()->underRepair()->create();
        $manager = User::factory()->labManager($item->lab)->create();
        $log = MaintenanceLog::factory()->for($item)->unresolved()->create(['reported_by' => $manager->id]);
        $this->actingAs($manager)->delete('/profile', ['password' => 'password'])->assertRedirect('/');
        $this->assertSoftDeleted('users', ['id' => $manager->id]);
        $this->assertSame($manager->id, $log->fresh()->reporter->id);
    }

    public function test_checkpoint_requires_current_role_revokes_tokens_and_enforces_session_limits(): void
    {
        $head = User::factory()->departmentHead()->create();
        $auditor = User::factory()->securityAuditor()->create(['department_id' => $head->department_id]);
        $old = $auditor->createToken('old', ['checkpoint:verify']);
        $auditor->forceFill(['role' => Role::Student])->save();
        $this->getJson('/api/reservations/active', ['Authorization' => 'Bearer '.$old->plainTextToken])->assertForbidden();

        Auth::forgetGuards();
        $auditor->forceFill(['role' => Role::SecurityAuditor])->save();
        $this->actingAs($head)->patch(route('department.users.update', $auditor), ['role' => 'student'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $old->accessToken->id]);

        Auth::forgetGuards();
        $auditor->forceFill(['role' => Role::SecurityAuditor])->save();
        $expired = $auditor->createToken('expired', ['checkpoint:verify']);
        $expired->accessToken->forceFill(['created_at' => now()->subHours(13)])->save();
        $this->getJson('/api/reservations/active', ['Authorization' => 'Bearer '.$expired->plainTextToken])
            ->assertUnauthorized();

        $credentials = ['email' => $auditor->email, 'password' => 'incorrect'];
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->postJson('/api/login', $credentials)->assertUnprocessable();
        }
        $this->postJson('/api/login', $credentials)->assertTooManyRequests();
    }

    public function test_reservation_limits_bound_duration_horizon_and_pending_count(): void
    {
        $this->freezeTime();
        $item = Equipment::factory()->create();
        $borrower = User::factory()->student()->create();
        $this->actingAs($borrower);
        $route = route('reservations.store', $item);
        $start = now()->addDay();

        $this->post($route, ['starts_at' => $start->toDateTimeString(), 'ends_at' => $start->copy()->addHours(25)->toDateTimeString()])
            ->assertSessionHasErrors('ends_at');
        $this->post($route, ['starts_at' => now()->addDays(91)->toDateTimeString(), 'ends_at' => now()->addDays(91)->addHour()->toDateTimeString()])
            ->assertSessionHasErrors('starts_at');

        Reservation::factory()->count(5)->for($borrower, 'user')->create();
        $this->post($route, ['starts_at' => $start->toDateTimeString(), 'ends_at' => $start->copy()->addHour()->toDateTimeString()])
            ->assertSessionHasErrors('starts_at');
        $this->assertSame(5, $borrower->reservations()->count());
    }

    public function test_expired_approval_and_early_or_double_handover_are_blocked(): void
    {
        $this->freezeTime();
        $item = Equipment::factory()->create();
        $manager = User::factory()->labManager($item->lab)->create();
        $stale = Reservation::factory()->for($item)->create(['starts_at' => now()->subHours(2), 'ends_at' => now()->subHour()]);
        $expiredApproval = Reservation::factory()->for($item)->approved()->create([
            'starts_at' => now()->subHours(2), 'ends_at' => now()->subMinute(),
        ]);
        $early = Reservation::factory()->for($item)->approved()->create(['starts_at' => now()->addHour(), 'ends_at' => now()->addHours(2)]);
        $due = Reservation::factory()->for($item)->approved()->create(['starts_at' => now()->subMinutes(5), 'ends_at' => now()->addMinutes(10)]);
        $overdue = Reservation::factory()->for($item)->checkedOut()->create(['starts_at' => now()->subHours(3), 'ends_at' => now()->subHours(2)]);

        $this->actingAs($manager)->patch(route('reservations.approve', $stale))->assertSessionHasErrors('starts_at');
        $this->patch(route('reservations.handover', $early))->assertSessionHasErrors('starts_at');
        $this->patch(route('reservations.handover', $expiredApproval))->assertSessionHasErrors('starts_at');
        $this->patch(route('reservations.handover', $due))->assertSessionHasErrors('equipment');
        $this->artisan('qol:notify')->assertSuccessful();
        $this->assertSame(ReservationStatus::Cancelled, $stale->fresh()->status);
        $this->assertSame(ReservationStatus::Cancelled, $expiredApproval->fresh()->status);
        $this->assertSame(ReservationStatus::Approved, $due->fresh()->status);
        $this->assertSame($overdue->id, $item->currentCheckout()->id);
    }

    public function test_breakdown_notifies_the_approved_borrower_and_preserves_the_booking(): void
    {
        $item = Equipment::factory()->create();
        $booking = Reservation::factory()->for($item)->approved()->create();
        $manager = User::factory()->labManager($item->lab)->create();

        $this->actingAs($manager)->post(route('maintenance.store'), [
            'equipment_id' => $item->id, 'issue' => 'Power supply failed.',
        ])->assertSessionHasNoErrors();

        $this->assertSame(ReservationStatus::Approved, $booking->fresh()->status);
        $this->assertTrue($booking->user->notifications()
            ->where('data->kind', 'maintenance')->where('data->reservation_id', $booking->id)->exists());
    }

    public function test_active_lab_closure_can_be_shortened_without_moving_its_start(): void
    {
        $this->freezeTime();
        $lab = Lab::factory()->create();
        $manager = User::factory()->labManager($lab)->create();
        $closure = LabClosure::create([
            'lab_id' => $lab->id, 'created_by' => $manager->id, 'reason' => 'Inspection',
            'starts_at' => now()->subHour(), 'ends_at' => now()->addHours(2),
        ]);

        $ends = now()->addMinutes(30)->toDateTimeString();
        $this->actingAs($manager)->get(route('lab-closures.index'))->assertOk()
            ->assertSee(route('lab-closures.update', $closure));
        $this->patch(route('lab-closures.update', $closure), [
            'lab_id' => $lab->id, 'reason' => 'Inspection finishing early',
            'starts_at' => $closure->starts_at->format('Y-m-d\TH:i:s'), 'ends_at' => $ends,
        ])->assertSessionHasNoErrors();
        $this->assertSame($ends, $closure->fresh()->ends_at->toDateTimeString());
    }

    public function test_borrower_can_retry_a_future_rejected_or_cancelled_request_immediately(): void
    {
        $borrower = User::factory()->student()->create();
        $this->actingAs($borrower);

        foreach ([ReservationStatus::Rejected, ReservationStatus::Cancelled] as $status) {
            $reservation = Reservation::factory()->for($borrower, 'user')->status($status)->create();
            $this->get(route('reservations.again', $reservation))->assertOk()->assertSee('Reserve again');
        }
    }

    public function test_csv_case_collisions_and_lossless_transport_are_handled(): void
    {
        $lab = Lab::factory()->create();
        $this->actingAs(User::factory()->labManager($lab)->create());
        $import = route('equipment.tools.import');
        $file = UploadedFile::fake()->createWithContent('equipment.csv', "name,serial_number\nOne,Mix-1\nTwo,mix-1\n");
        $this->post($import, ['file' => $file])
            ->assertSessionHasErrors(['file' => 'Row 3: duplicate serial number mix-1 in this file.']);
        $this->assertDatabaseMissing('equipment', ['serial_number' => 'Mix-1']);

        $item = Equipment::factory()->for($lab)->create(['name' => "'Original", 'serial_number' => '-001']);
        $tag = Tag::factory()->create(['name' => 'Audio|Video']);
        $item->tags()->attach($tag);
        $csv = $this->get(route('equipment.tools.export', ['format' => 'roundtrip']))->assertDownload()->streamedContent();
        $this->assertStringStartsWith('name_b64,serial_b64,tags_json_b64', $csv);
        $item->delete();
        $this->post($import, ['file' => UploadedFile::fake()->createWithContent('equipment.csv', $csv)])
            ->assertSessionHasNoErrors();
        $copy = Equipment::where('serial_number', '-001')->firstOrFail();
        $this->assertSame("'Original", $copy->name);
        $this->assertSame(['Audio|Video'], $copy->tags()->pluck('name')->all());
    }

    public function test_equipment_removal_cleans_its_unshared_files_and_keeps_shared_documents(): void
    {
        Storage::fake('public')->put('equipment/audit.png', 'photo');
        Storage::fake('local')->put('documents/audit.pdf', 'document');
        Storage::disk('local')->put('documents/shared.pdf', 'shared');
        $item = Equipment::factory()->create(['photo_path' => 'equipment/audit.png']);
        Document::factory()->for($item)->create(['file_path' => 'documents/audit.pdf']);
        Document::factory()->for($item)->create(['file_path' => 'documents/shared.pdf']);
        Document::factory()->create(['file_path' => 'documents/shared.pdf']);
        $manager = User::factory()->labManager($item->lab)->create();

        $this->actingAs($manager)->delete(route('equipment.destroy', $item))->assertRedirect(route('equipment.index'));
        Storage::disk('public')->assertMissing('equipment/audit.png');
        Storage::disk('local')->assertMissing('documents/audit.pdf');
        Storage::disk('local')->assertExists('documents/shared.pdf');
    }

    public function test_role_lab_and_tag_administration_write_scoped_audit_records(): void
    {
        $lab = Lab::factory()->create();
        $head = User::factory()->departmentHead($lab->department)->create();
        $student = User::factory()->student()->create(['department_id' => $head->department_id]);
        $this->actingAs($head);
        $this->patch(route('department.users.update', $student), ['role' => 'faculty'])->assertSessionHasNoErrors();
        $this->post(route('department.labs.store'), ['name' => 'Audit lab', 'code' => 'AUD-LAB'])->assertSessionHasNoErrors();
        $this->post(route('tags.store'), ['name' => 'Audit tag'])->assertSessionHasNoErrors();
        foreach (['user.role-updated', 'lab.created', 'tag.created'] as $event) {
            $this->assertDatabaseHas('activity_logs', ['event' => $event, 'actor_id' => $head->id]);
        }
    }

    public function test_in_flight_catalog_request_does_not_restore_disabled_preferences(): void
    {
        $lab = Lab::factory()->create();
        $user = User::factory()->student()->create(['preferences' => ['remember_filters' => true]]);
        $changed = false;
        Lab::retrieved(function (Lab $seen) use ($lab, $user, &$changed) {
            if ($seen->is($lab) && ! $changed) {
                $changed = true;
                DB::table('users')->where('id', $user->id)->update([
                    'preferences' => json_encode(['remember_filters' => false, 'theme' => 'dark']),
                ]);
            }
        });

        $this->actingAs($user)->get(route('equipment.index', ['q' => 'Concurrent search']))->assertOk();
        $this->assertTrue($changed);
        $this->assertSame(['remember_filters' => false, 'theme' => 'dark'], $user->fresh()->preferences);
    }

    public function test_wishlist_availability_batches_recipients_without_losing_notifications(): void
    {
        $item = Equipment::factory()->create();
        $borrowers = User::factory()->count(8)->student()->create();
        foreach ($borrowers as $borrower) {
            $borrower->wishlist()->attach($item);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            AvailabilityNotifier::notify($item);
            $reads = collect(DB::getQueryLog())->filter(fn ($query) => str_contains($query['query'], 'from "users"'))->count();
        } finally {
            DB::disableQueryLog();
        }
        $this->assertLessThanOrEqual(9, $reads);
        $this->assertDatabaseCount('notifications', 8);
    }

    public function test_catalog_model_pagination_does_not_hydrate_all_matching_equipment(): void
    {
        $lab = Lab::factory()->create();
        foreach (range(1, 24) as $i) {
            foreach ([1, 2] as $unit) {
                Equipment::factory()->for($lab)->create(['name' => "Audit Model {$i} #{$unit}"]);
            }
        }
        $hydrated = 0;
        Equipment::retrieved(function () use (&$hydrated) {
            $hydrated++;
        });

        $this->actingAs(User::factory()->student()->create())
            ->get(route('equipment.index', ['q' => 'Audit Model']))->assertOk()
            ->assertSee('Showing 1–12 of 24')->assertSee('Quantity: 2')->assertSee('Available: 2');
        $this->assertLessThanOrEqual(12, $hydrated);
    }

    public function test_catalog_treats_percent_and_underscore_as_literal_search_text(): void
    {
        Equipment::factory()->create(['name' => 'Calibration 100%_A', 'serial_number' => 'OPT_01']);
        Equipment::factory()->create(['name' => 'Calibration 100XXA', 'serial_number' => 'OPTX01']);

        $this->actingAs(User::factory()->student()->create())
            ->get(route('equipment.index', ['q' => '100%_A']))->assertOk()
            ->assertSee('Calibration 100%_A')->assertDontSee('Calibration 100XXA');
        $this->get(route('equipment.index', ['q' => 'OPT_01']))->assertOk()
            ->assertSee('Calibration 100%_A')->assertDontSee('Calibration 100XXA');
        $this->get(route('equipment.index', ['model' => 'Calibration 100%_A']))->assertOk()
            ->assertSee('Calibration 100%_A')->assertDontSee('Calibration 100XXA');
    }

    public function test_department_cannot_delete_a_lab_with_closure_history(): void
    {
        $lab = Lab::factory()->create();
        $head = User::factory()->departmentHead($lab->department)->create();
        $closure = LabClosure::create([
            'lab_id' => $lab->id, 'created_by' => $head->id, 'reason' => 'Safety inspection',
            'starts_at' => now()->subDays(2), 'ends_at' => now()->subDay(),
        ]);

        $this->actingAs($head)->delete(route('department.labs.destroy', $lab))->assertSessionHasErrors('lab');
        $this->assertDatabaseHas('lab_closures', ['id' => $closure->id]);
    }

    public function test_checkpoint_denies_checkout_authorization_at_exact_end_time(): void
    {
        $this->freezeSecond();
        $checkout = Reservation::factory()->checkedOut()->create(['starts_at' => now()->subHour(), 'ends_at' => now()]);
        $auditor = User::factory()->securityAuditor()->create();
        $token = $auditor->createToken('checkpoint', ['checkpoint:verify']);
        $this->getJson('/api/equipment/'.$checkout->equipment->qr_code.'/authorization', [
            'Authorization' => 'Bearer '.$token->plainTextToken,
        ])->assertOk()->assertJsonPath('data.authorized', false);
    }
}
