# Equip Me Maybe bug audit and change record

## Integration on main

The confirmed fixes and 16 runnable regressions are part of this checkout. Run the migrations to enable account history retention and backfill the catalog grouping keys. The SQL catalog groups numbered units while keeping individual cards for standalone items. The reservation limits are 24 hours per booking, a 90-day start horizon, and five open pending requests per borrower. Checkpoint tokens default to 12 hours and use the existing UTC clock.

On the integrated source, PHPUnit reported 455 passed and one MySQL-specific skip. All 112 browser checks and the production build passed. A SQLite upgrade from the previous schema backfilled catalog keys for existing equipment.

## Scope and verification

The audit used the source extracted with Python from Git commit `dbdda9f` on `test--Ibay`. The app, routes, config, migrations, and test files match `fe11f08`, the grouped-catalog commit. The reachable snapshot has 33 feature-test PHP files, including tests under `tests/Feature/Auth`. The proposed bug fixes were exercised in a disposable checkout. The earlier local catalog and interface commits are recorded at the end.

| Check | Passed | Failed | Skipped |
|---|---:|---:|---:|
| Original full PHPUnit suite | 431 | 7 | 1 |
| New regression tests against original source | 0 | 16 | 0 |
| New regression tests with the proposed fixes | 16 | 0 | 0 |
| Full PHPUnit suite with proposed fixes and fixture updates | 447 | 7 | 1 |

The seven full-suite failures are present in the original snapshot. They cover four role-specific catalog availability cases, wishlist removal, invalid scalar filters, and catalog pagination. Five existing test methods received compatibility updates for soft deletion or the handover window. The runs used PHPUnit 11.5.57 on PHP 8.4.27 with SQLite. The dedicated MySQL/MariaDB concurrency test was skipped. Production collation and concurrency behavior still need verification on the target database.

The complete patch is `/workspace/work/equip-me-maybe-bug-audit.patch`. It applies to `dbdda9f`. The fixed files are in the disposable checkout at `/workspace/work/audit`. The complete PHPUnit class is `tests/Feature/AuditRegressionTest.php` there. The examples below show the core Laravel changes and the exact new test methods.

## Summary

| ID | Verdict | Severity | Source |
|---|---|---|---|
| 1 | CONFIRMED | High | `ProfileController::destroy` and the reservations and maintenance migrations |
| 2 | CONFIRMED | High | `AuthTokenController::store`, `UserRoleController::update`, `routes/api.php`, `config/sanctum.php` |
| 3 | PARTIAL | High if campus-only | `RegisteredUserController::store`, `UserRoleController::index/update`, `User` |
| 4 | CONFIRMED | High | `StoreReservationRequest::rules`, `ReservationService::request` |
| 5 | CONFIRMED | High | `ReservationService::approve/handOver`, `Equipment::currentCheckout`, `NotifyBorrowers::handle` |
| 6 | CONFIRMED | Medium | `MaintenanceLogController::store` |
| 7 | CONFIRMED | Medium | `ClosureRequest::rules`, `LabClosureController::update`, closure index view |
| 8 | CONFIRMED | Low | `Reservation::canReserveAgain`, `ReservationController::again` |
| 9 | CONFIRMED | Medium | `EquipmentToolsController::import/export/csvDownload` |
| 10 | CONFIRMED | Medium | `EquipmentController::destroy`, `DocumentController::store/destroy` |
| 11 | CONFIRMED | Medium | `UserRoleController::update`, `LabAdminController` and `TagAdminController` mutations |
| 12 | CONFIRMED | Medium | `RememberListPreferences::handle`, `PreferencesController::update` |
| 13 | CONFIRMED | Medium | `AvailabilityNotifier::notify`, `NotifyBorrowers::handle` |
| 14 | REFUTED as a defect | None | `config/app.php` |
| 15 | CONFIRMED | Medium | `EquipmentController::paginateCatalogModels` |
| 16 | CONFIRMED | Low | `EquipmentController::index/applyCatalogSearch/matchCatalogName/matchUnitIdentifiers` |
| 17 | PARTIAL | Low | `config/equipment.php`, `EquipmentController::matchCatalogName` |
| A | CONFIRMED | Medium | `LabAdminController::destroy`, lab-closure migration |
| B | CONFIRMED | Medium | `Reservation::isAuthorizedNow`, `CheckpointController::authorization` |

## Higher priority

### 1. Preserve history when an account is deleted

`app/Http/Controllers/ProfileController.php::destroy` hard-deleted users. The foreign keys in `2026_11_12_000007_create_reservations_table.php` and `2026_11_12_000008_create_maintenance_logs_table.php` cascaded on borrower and reporter deletion. A checkout or repair could disappear while the item stayed reserved or under repair.

Add the `deleted_at` migration and `SoftDeletes` on `User`. Historical user relations use `withTrashed()`. Inside the locked transaction, refuse deletion while pending, approved, or checked-out reservations exist. Revoke tokens before soft deletion. Retaining the account row preserves foreign keys and history. The retained email stays unique, so registration with that email requires a campus retention or reactivation policy.

```php
$user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
if ($user->reservations()->whereIn('status', ['pending', 'approved', 'checked_out'])->exists()) {
    throw ValidationException::withMessages([
        'password' => 'Resolve your open reservations before deleting your account.',
    ])->errorBag('userDeletion');
}
$user->tokens()->delete();
$user->delete();
```

#### PHPUnit regression

```php
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
```

### 2. Tie checkpoint access to the current auditor role

`routes/api.php` checked the Sanctum ability after authentication. `AuthTokenController::store` checked the auditor role only during issuance. A demoted account could use an existing token. `config/sanctum.php` had no expiry and the login route had no limiter.

Add a live role gate, six login attempts per minute, and a configurable default TTL of 720 minutes. Lock the target user during issuance and role changes. Revoke tokens when a role changes. The tested defaults are examples for a campus checkpoint shift.

```php
// config/sanctum.php
'expiration' => (int) env('CHECKPOINT_TOKEN_TTL_MINUTES', 720),

// AppServiceProvider::boot
Gate::define('verify_checkpoint', fn (User $user) => $user->hasRole(Role::SecurityAuditor));

// routes/api.php
Route::post('/login', [AuthTokenController::class, 'store'])->middleware('throttle:6,1');
Route::middleware(['auth:sanctum', 'abilities:checkpoint:verify', 'can:verify_checkpoint'])
    ->group(/* existing protected routes */);

// UserRoleController::update, inside the role-change transaction
if ($user->wasChanged('role')) {
    $user->tokens()->delete();
}
```

#### PHPUnit regression

```php
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
```

### 3. Decide how campus affiliation is established

`RegisteredUserController::store` accepts a valid email and signs the user in. `User` lacks the `MustVerifyEmail` contract and the protected web route group lacks `verified`. The model defaults to student and excludes role and department from mass assignment. `UserRoleController::index` lists unassigned non-head accounts, including names and emails, and `update` lets an authenticated head assign them. An outsider can register and make student requests. Privileged assignment still requires a department head. The existing role regression deliberately exercises unassigned-user assignment.

For campus-only access, use the campus identity provider or verified invitations tied to a preassigned department. Email verification is a useful first step for mailbox ownership. Campus affiliation needs a separate trusted check. The tested patch preserves the registration and assignment policy and serializes competing claims by locking and rechecking the target user in `update`.

### 4. Bound reservation length, horizon, and pending volume

`StoreReservationRequest::rules` checks a future start and an end after the start. `ReservationService::request` had no length, horizon, or pending limit. Individual borrowers could occupy large windows or accumulate pending work.

Enforce the limits in the service so every caller gets the same policy. The prototype uses 24 hours, a 90-day start horizon, and five open pending requests. Apply the quota under a borrower lock after the equipment and lab checks so simultaneous requests for different equipment share the same limit.

```php
if ($start->lt(now()) || $start->gt(now()->addDays(90))) {
    throw ValidationException::withMessages(['starts_at' => 'Choose a start time within the next 90 days.']);
}
if ($end->lte($start) || $end->gt($start->copy()->addHours(24))) {
    throw ValidationException::withMessages(['ends_at' => 'Reservations may last up to 24 hours.']);
}
$user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
abort_unless($user->isBorrower(), 403);
$pending = $user->reservations()->where('status', ReservationStatus::Pending)
    ->where('ends_at', '>', now())->lockForUpdate()->pluck('id');
if ($pending->count() >= 5) {
    throw ValidationException::withMessages(['starts_at' => 'Resolve a pending request before making another.']);
}
```

#### PHPUnit regression

```php
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
```

### 5. Enforce the reservation window and one checkout per item

`ReservationService::approve` accepted past-start pending requests. `handOver` lacked time and current-checkout checks. Because `Equipment::currentCheckout` chooses the latest start, a second checkout could displace the real holder in the checkpoint response. The scheduled `NotifyBorrowers::handle` did not expire unused reservations.

Run the time and occupancy checks under the existing equipment lock. In the scheduled command, find pending and approved windows whose end has elapsed, call `ReservationService::expire` and recheck status and end time under the same item lock. The expiry records cancellation and sends an in-app notification through the existing transition recorder. Overdue checked-out items remain due for physical return. Existing duplicate checkout records need a manual inventory reconciliation.

```php
// approve(), after a fresh pending-state check
if ($reservation->starts_at->lt(now())) {
    throw ValidationException::withMessages(['starts_at' => 'This reservation has already started.']);
}

// handOver(), after the repair and compliance checks
$now = now();
if ($now->lt($reservation->starts_at) || $now->gte($reservation->ends_at)) {
    throw ValidationException::withMessages(['starts_at' => 'Handover is only possible during the reservation.']);
}
if ($equipment->reservations()->checkedOut()->exists()) {
    throw ValidationException::withMessages(['equipment' => 'Receive the current checkout before handing over this item.']);
}
```

#### PHPUnit regression

```php
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
```

### 6. Notify borrowers when reserved equipment enters repair

`MaintenanceLogController::store` sets `UnderRepair` and refreshes wishlist availability. Future approved borrowers received no direct notice. Handover still enforces the repair gate.

Keep approvals for short repairs. Send an in-app message to each borrower with an approved booking whose end is still upcoming. Use the maintenance-log and reservation IDs as the deduplication key. The booking then stays visible and the borrower has an actionable status update.

```php
$equipment->reservations()->where('status', ReservationStatus::Approved)
    ->where('ends_at', '>', now())->with('user')->eachById(function ($booking) use ($log, $equipment) {
        if ($booking->user?->isBorrower()) {
            NotificationDispatcher::once($booking->user, "repair:{$log->id}:{$booking->id}", [
                'kind' => 'maintenance', 'title' => 'Reserved equipment under repair',
                'message' => $equipment->name.' cannot be handed over until the repair is resolved.',
                'url' => route('reservations.show', $booking, false),
                'reservation_id' => $booking->id, 'equipment_id' => $equipment->id,
            ]);
        }
    });
```

#### PHPUnit regression

```php
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
```

## Medium and low priority

### 7. Let managers shorten closures already in progress

`ClosureRequest::rules` applied the future-start constraint to edits. `resources/views/lab-closures/index.blade.php` also hid the edit form once a closure started. Allow the form until the closure ends, make its start and lab immutable once underway, and continue validating conflicts in `LabClosureController::update`. This supports reopening a lab early while preserving its recorded closure history.

```php
$closure = $this->route('lab_closure');
$started = $closure && $closure->starts_at->lte(now());
// Add Rule::in([$closure->lab_id]) to lab_id when $started.
'starts_at' => ['required', 'date', $started
    ? 'date_equals:'.$closure->starts_at->toDateTimeString()
    : 'after_or_equal:now'],
'ends_at' => ['required', 'date', 'after:starts_at', 'after_or_equal:now'],
```

#### PHPUnit regression

```php
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
```

### 8. Enable immediate retry after rejection or cancellation

`Reservation::canReserveAgain` required the original end to be past even for a terminal rejection or cancellation. `ReservationController::again` prefills a new time, which is then validated as a new request. Keep borrower ownership and the existing wait on returned bookings. Permit the owner to retry a cancelled or rejected request immediately.

```php
return $user->isBorrower() && $this->user_id === $user->id
    && in_array($this->status, [ReservationStatus::Returned, ReservationStatus::Rejected, ReservationStatus::Cancelled], true)
    && ($this->status !== ReservationStatus::Returned || $this->ends_at->isPast());
```

#### PHPUnit regression

```php
public function test_borrower_can_retry_a_future_rejected_or_cancelled_request_immediately(): void
{
    $borrower = User::factory()->student()->create();
    $this->actingAs($borrower);

    foreach ([ReservationStatus::Rejected, ReservationStatus::Cancelled] as $status) {
        $reservation = Reservation::factory()->for($borrower, 'user')->status($status)->create();
        $this->get(route('reservations.again', $reservation))->assertOk()->assertSee('Reserve again');
    }
}
```

### 9. Separate spreadsheet export from lossless transport

`EquipmentToolsController::import` keyed duplicates by the raw PHP string. The default MySQL/MariaDB collation is `utf8mb4_unicode_ci`, so case-only duplicates can reach the wrong error path. `csvDownload` prefixes formula-like values to protect spreadsheet readers. The pipe-delimited tag list cannot represent a literal pipe.

Use case-folded serial keys for preflight and retain the DB unique constraint for concurrent inserts and collation-specific conflicts. Add an explicit `format=roundtrip` export with prefixed base64 name and serial cells and a base64 JSON tag array. Decode only when the exact special header is present. Retain spreadsheet formula protection on the original export. Ordinary imports keep accepting the existing format.

```php
$row['serial_key'] = mb_strtolower($row['serial_number'], 'UTF-8');
if (isset($seen[$row['serial_key']])) {
    $errors[] = "Row {$line}: duplicate serial number {$row['serial_number']} in this file.";
}
$seen[$row['serial_key']] = true;

// Lossless export recipe for the explicit roundtrip format
$headers = ['name_b64', 'serial_b64', 'tags_json_b64'];
$row = array_map(
    fn (string $value) => 'b64:'.base64_encode($value),
    [$item->name, $item->serial_number, json_encode($item->tags->pluck('name')->all(), JSON_THROW_ON_ERROR)],
);
```

#### PHPUnit regression

```php
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
```

### 10. Clean up files when unused equipment is deleted

`EquipmentController::destroy` removed the equipment and cascaded document rows without cleaning up their paths. `DocumentController::destroy` already showed why a shared-path check matters for seeded PDFs.

Collect paths before deleting the rows and remove only unreferenced files after commit. Continue refusing equipment deletion with reservation or repair history. Document uploads share the parent equipment lock and clean up their uploaded file if the DB write fails.

```php
$photo = $equipment->photo_path;
$files = $equipment->documents()->distinct()->pluck('file_path');
$equipment->delete();
DB::afterCommit(function () use ($photo, $files) {
    if ($photo && ! Equipment::where('photo_path', $photo)->exists()) {
        Storage::disk('public')->delete($photo);
    }
    foreach ($files as $path) {
        if (! Document::where('file_path', $path)->exists()) {
            Storage::disk('local')->delete($path);
        }
    }
});
```

#### PHPUnit regression

```php
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
```

### 11. Record changes to roles, laboratories, and tags

`UserRoleController::update` and the mutations in `LabAdminController` and `TagAdminController` wrote data without recording an activity. Add activity rows inside the mutation transaction. Include actor and department scope and lab scope where applicable. This lets the existing `ActivityLog::scopeForViewer` filter history correctly and makes access and inventory changes attributable.

```php
ActivityRecorder::record('lab.created', $request->user(), details: [
    'lab_id' => $lab->id, 'department_id' => $lab->department_id, 'name' => $lab->name,
]);
ActivityRecorder::record('user.role-updated', $head, details: [
    'user_id' => $user->id, 'department_id' => $user->department_id,
    'lab_id' => $user->lab_id, 'from_role' => $before->value, 'to_role' => $user->role->value,
]);
```

#### PHPUnit regression

```php
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
```

### 12. Serialize preference updates and honor the current opt-out

`RememberListPreferences::handle` and `PreferencesController::update` could read and rewrite the entire JSON document concurrently. The middleware also decided whether to save before the request was processed, so an in-flight request could restore a list after a user disabled remembering.

Lock the same user row for both writers, re-read the current preferences, and re-check consent before writing the list. Update the authenticated model instance after commit so later code sees the saved preferences. The test deterministically simulates opt-out between request start and the middleware save. Production locking behavior needs the MySQL/MariaDB run.

```php
$updated = DB::transaction(function () use ($user, $name, $saved) {
    $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
    $preferences = $locked->preferences ?? [];
    if ($preferences['remember_filters'] ?? true) {
        $preferences['lists'][$name] = $saved;
        $locked->forceFill(['preferences' => $preferences])->save();
    }
    return $preferences;
}, 3);
$user->forceFill(['preferences' => $updated])->syncOriginalAttribute('preferences');
```

#### PHPUnit regression

```php
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
```

### 13. Batch wishlist recipient lookups

`AvailabilityNotifier::notify` fetched users one at a time inside the item transaction and ran inline on lifecycle changes. `NotifyBorrowers::handle` calls it for every wishlisted item each minute. The original test made 16 user reads for eight notifications. Chunk waiting wishlist rows, fetch their users in a batch, and update notified timestamps per batch. The test bounds user reads at nine while preserving all eight notifications.

```php
DB::table('wishlists')->where('equipment_id', $equipment->id)->whereNull('notified_at')
    ->chunkById(100, function ($entries) {
        $borrowers = User::whereKey($entries->pluck('user_id')->all())->get()->keyBy('id');
        // Pass each eligible borrower to the existing deduplicating dispatcher.
        // Bulk-update the processed entries' notification timestamps.
    });
```

The scheduler continues to detect time-driven availability. For larger inventories, profile its latency and consider a transactionally recorded, queued availability update with a separate mechanism for time transitions.

#### PHPUnit regression

```php
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
```

### 14. Keep the intentional UTC contract

`config/app.php` fixes the application timezone to UTC, which matches the stated contract and the reservation date-guidance test. An environment override would introduce a deployment option with consequences for form inputs, calendar output, and stored timestamps. Treat it as a separate requirement if the university needs it.

## Catalog

### 15. Paginate model groups in SQL

`EquipmentController::paginateCatalogModels` loaded every matching unit and grouped then paginated in PHP. The new test saw 48 equipment models hydrated for a 12-group page. Persist and backfill a key derived from the existing normalized model name. Use SQL window functions to compute quantity, availability, first image, and the representative row. Paginate once per group. This bounds PHP hydration while keeping aggregate availability and the existing selected sort. SQL still processes matching units to compute their groups. Use MySQL 8+, MariaDB 10.2+ or SQLite 3.25+ for window support.

```php
private function paginateCatalogModels(Builder $catalog, string $sort, string $direction): LengthAwarePaginator
{
    // CatalogFilterRequest allowlists both values before building these expressions.
    $order = $catalog->getQuery()->getGrammar()->wrap($sort).' '.$direction.', id';
    $groups = DB::query()->fromSub((clone $catalog)->reorder()->toBase(), 'units')
        ->select(array_unique(['id', 'name', $sort]))
        ->selectRaw("ROW_NUMBER() OVER (PARTITION BY catalog_model_key ORDER BY {$order}) AS group_rank")
        ->selectRaw('COUNT(*) OVER (PARTITION BY catalog_model_key) AS quantity')
        ->selectRaw("FIRST_VALUE(photo_path) OVER (PARTITION BY catalog_model_key
            ORDER BY CASE WHEN NULLIF(TRIM(photo_path), '') IS NULL THEN 1 ELSE 0 END, {$order}) AS group_photo")
        ->selectRaw('SUM(CASE WHEN status <> ? AND has_open_repairs = 0 AND closure_until IS NULL
            AND checked_out_until IS NULL AND booked_until IS NULL THEN 1 ELSE 0 END)
            OVER (PARTITION BY catalog_model_key) AS available', ['under_repair']);

    return DB::query()->fromSub($groups, 'models')->where('group_rank', 1)
        ->orderBy($sort, $direction)->orderBy('id')->paginate(12)->withQueryString()
        ->through(fn (object $row) => [
            'name' => Equipment::normalizeCatalogModel($row->name),
            'photo_url' => filled($row->group_photo) ? Storage::disk('public')->url($row->group_photo) : null,
            'quantity' => (int) $row->quantity,
            'available' => (int) $row->available,
        ]);
}
```

#### PHPUnit regression

```php
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
```

### 16. Match percent signs and underscores literally

`EquipmentController::applyCatalogSearch`, `matchCatalogName`, and `matchUnitIdentifiers` passed user text as a SQL LIKE pattern. Bound parameters stop SQL syntax injection. The observed failure is that the two wildcard characters matched unrelated inventory. Escape the literal input before adding application wildcards and use the stored key for model drill-down.

```php
$literal = strtr($search, ['!' => '!!', '%' => '!%', '_' => '!_']);
$query->whereRaw("name LIKE ? ESCAPE '!'", ['%'.$literal.'%']);
// The same helper is used for serial, QR, lab, and department lookups.
```

#### PHPUnit regression

```php
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
```

### 17. Make campus categories data-driven where needed

`config/equipment.php` maps the search term `camera` to two product names, and `EquipmentController::matchCatalogName` applies the alias list. This works for those demo models. Coverage of other brand-only model names depends on their stored names and aliases. If the university expects category discovery by data, let staff apply existing equipment tags such as Camera and include those tags in catalog matching. Confirm the desired search precedence before changing the existing behavior.

## Additional findings

### A. Preserve laboratory closure history

`LabAdminController::destroy` checked for equipment and users but `2026_11_15_100020_create_lab_closures_table.php` cascades closure rows on lab deletion. An otherwise empty lab could lose its inspection or closure records. Extend the deletion check inside the existing lab lock.

```php
if (LabClosure::where('lab_id', $lab->id)->exists()) {
    throw ValidationException::withMessages(['lab' => 'Keep laboratories with closure history.']);
}
```

#### PHPUnit regression

```php
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
```

### B. End checkpoint authorization at the exact booking end

`Reservation::isAuthorizedNow` used `betweenIncluded` and authorized the borrower at exactly the end time. `scopeOverlapping` uses a half-open window so the next booking can start at that same instant. Use the same interval in both places.

```php
$now = now();
return $this->status === ReservationStatus::CheckedOut
    && $now->gte($this->starts_at) && $now->lt($this->ends_at);
```

#### PHPUnit regression

```php
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
```

## Earlier local changes

| Where | What changed | How and why |
|---|---|---|
| `da220ef`, equipment controller, catalog and unit views | Restored availability and wishlist actions, fixed filters and pagination | Individual cards show the equipment state and borrower actions. Numbered items can still be grouped. Pagination carries normalized filters. The earlier local suite passed with 439 tests and one skip. |
| `f0bae3a`, catalog, workspace layout, booking views and shared styles | Applied the video techniques selected for campus use | Search stays visible with expandable filters. Larger touch targets, keyboard focus restoration, aligned dates/counts, and reserved photo space support students and staff. That local branch passed 439 PHP tests and 112 browser checks. |

The audit patch uses the original PR head as its base. The earlier fixes and design changes are in the separate `/workspace/work/equip-maybe-pr3.patch`. The integrated catalog query uses SQL grouping and retains the individual-card behavior.

## Missing information

- The named `equip-me-maybe-git.zip` was absent. The matching tracked source was available from Git. The nested release archive was excluded.
- Campus identity policy, approved booking limits, repair cancellation policy, and device token lifetime need confirmation before deployment. The audit uses explicit provisional defaults.
- Test the SQL grouping, case handling, and row-lock paths against the actual MySQL/MariaDB version and collation before releasing them.
