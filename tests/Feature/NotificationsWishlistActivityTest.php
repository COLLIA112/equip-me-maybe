<?php

namespace Tests\Feature;

use App\Enums\EquipmentStatus;
use App\Enums\Role;
use App\Models\Equipment;
use App\Models\Lab;
use App\Models\LabClosure;
use App\Models\Reservation;
use App\Models\User;
use App\Services\ActivityRecorder;
use App\Services\AvailabilityNotifier;
use App\Services\NotificationDispatcher;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NotificationsWishlistActivityTest extends TestCase
{
    use RefreshDatabase;

    public static function roles(): array
    {
        return array_map(fn (Role $role) => [$role], Role::cases());
    }

    private function fixture(Role $role): array
    {
        $lab = Lab::factory()->create();
        $item = Equipment::factory()->for($lab)->create();
        $user = User::factory()->create(['role' => $role, 'department_id' => $lab->department_id, 'lab_id' => $role === Role::LabManager ? $lab->id : null]);

        return [$user, $item];
    }

    #[DataProvider('roles')]
    public function test_wishlist_is_borrower_only_and_add_remove_are_idempotent(Role $role): void
    {
        [$user, $item] = $this->fixture($role);
        $allowed = $user->isBorrower();
        $this->actingAs($user)->get(route('wishlist.index'))->assertStatus($allowed ? 200 : 403);
        $response = $this->post(route('wishlist.store', $item));
        if (! $allowed) {
            $response->assertForbidden();
            $this->delete(route('wishlist.destroy', $item))->assertForbidden();
            $this->assertDatabaseCount('wishlists', 0);

            return;
        }
        $response->assertSessionHasNoErrors()->assertRedirect();
        $this->post(route('wishlist.store', $item))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('wishlists', 1);
        $this->get(route('wishlist.index'))->assertOk()->assertSee($item->name)->assertSee('Remove from wishlist')->assertSee('Showing 1–1 of 1');
        $this->delete(route('wishlist.destroy', $item))->assertSessionHasNoErrors();
        $this->delete(route('wishlist.destroy', $item))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('wishlists', 0);
    }

    #[DataProvider('roles')]
    public function test_notifications_and_mark_read_actions_are_owner_scoped_for_all_web_roles(Role $role): void
    {
        [$user] = $this->fixture($role);
        $other = User::factory()->student()->create();
        NotificationDispatcher::once($user, 'own', ['title' => 'Own notice', 'message' => 'Private own text', 'url' => '/equipment']);
        NotificationDispatcher::once($other, 'other', ['title' => 'Other notice', 'message' => 'Private foreign text']);
        $own = $user->notifications()->first();
        $foreign = $other->notifications()->first();
        $this->actingAs($user);
        if ($role === Role::SecurityAuditor) {
            $this->get(route('notifications.index'))->assertForbidden();
            $this->patch(route('notifications.read', $own->id))->assertForbidden();
            $this->post(route('notifications.read-all'))->assertForbidden();

            return;
        }
        $this->get(route('notifications.index'))->assertOk()->assertSee('Own notice')->assertDontSee('Private foreign text');
        $this->patch(route('notifications.read', $foreign->id))->assertForbidden();
        $this->patch(route('notifications.read', 'not-a-notification'))->assertForbidden();
        $this->patch(route('notifications.read', $own->id))->assertSessionHasNoErrors();
        $this->assertNotNull($own->fresh()->read_at);
        NotificationDispatcher::once($user, 'second', ['title' => 'Another own notice']);
        $this->post(route('notifications.read-all'))->assertSessionHasNoErrors();
        $this->assertSame(0, $user->unreadNotifications()->count());
        $this->assertNull($foreign->fresh()->read_at);
        $this->get(route('notifications.index'))->assertOk()->assertSee('0 unread');
    }

    #[DataProvider('roles')]
    public function test_visible_activity_never_leaks_outside_own_reservation_lab_or_department(Role $role): void
    {
        [$user, $item] = $this->fixture($role);
        $own = Reservation::factory()->for($item)->for($user, 'user')->create();
        $foreign = Reservation::factory()->create();
        ActivityRecorder::record('Own-event', $user, $item, $own, ['note' => 'Own history']);
        ActivityRecorder::record('Foreign-event', $foreign->user, $foreign->equipment, $foreign, ['note' => 'Forbidden foreign details']);
        $response = $this->actingAs($user)->get(route('activities.index'));
        if ($role === Role::SecurityAuditor) {
            $response->assertForbidden();

            return;
        }
        $response->assertOk()->assertSee('Own history')->assertDontSee('Forbidden foreign details')->assertSee('Showing 1–1 of 1');
        $this->get(route('activities.index', ['reservation_id' => $foreign->id]))->assertOk()->assertDontSee('Forbidden foreign details');
        $this->getJson(route('activities.index', ['equipment_id' => ['bad']]))->assertUnprocessable();
    }

    public function test_in_app_return_reminders_respect_time_boundaries_and_are_not_duplicated_after_reading(): void
    {
        $this->travelTo(now()->setSeconds(0));
        $student = User::factory()->student()->create();
        $faculty = User::factory()->faculty()->create();
        $due = Reservation::factory()->for($student, 'user')->checkedOut()->create(['ends_at' => now()->addHours(2)]);
        $overdue = Reservation::factory()->for($faculty, 'user')->checkedOut()->create(['ends_at' => now()->subMinute()]);
        Reservation::factory()->for($student, 'user')->checkedOut()->create(['ends_at' => now()->addHours(2)->addSecond()]);
        Reservation::factory()->for($student, 'user')->approved()->create(['ends_at' => now()->addHour()]);
        Reservation::factory()->for($student, 'user')->returned()->create(['ends_at' => now()->subMinute()]);
        $this->artisan('qol:notify')->assertSuccessful();
        $this->assertSame(1, $student->notifications()->count());
        $this->assertSame($due->id, $student->notifications()->first()->data['reservation_id']);
        $this->assertSame('overdue', $faculty->notifications()->first()->data['kind']);
        $student->notifications()->first()->markAsRead();
        $this->artisan('qol:notify')->assertSuccessful();
        $this->assertDatabaseCount('notifications', 2);
        $this->actingAs($student)->get(route('dashboard'))->assertOk()->assertSee('Return reminders')->assertSee('Due back');
        $this->actingAs($faculty)->get(route('dashboard'))->assertOk()->assertSee('Overdue since');
        $this->travel(121)->minutes();
        $this->artisan('qol:notify')->assertSuccessful();
        $this->assertSame(2, $student->notifications()->where('data->reservation_id', $due->id)->count());
        $this->assertSame($overdue->id, $faculty->notifications()->first()->data['reservation_id']);
    }

    public function test_wishlist_waits_for_repair_checkout_and_closure_then_sends_once_per_cycle(): void
    {
        [$student, $item] = $this->fixture(Role::Student);
        $item->update(['status' => EquipmentStatus::UnderRepair]);
        $this->actingAs($student)->post(route('wishlist.store', $item))->assertSessionHasNoErrors();
        AvailabilityNotifier::notify($item);
        $this->assertDatabaseCount('notifications', 0);
        $item->update(['status' => EquipmentStatus::Available]);
        $overdue = Reservation::factory()->for($item)->checkedOut()->create(['ends_at' => now()->subMinute()]);
        AvailabilityNotifier::notify($item);
        $this->assertDatabaseCount('notifications', 0);
        $overdue->update(['status' => 'returned']);
        $closure = LabClosure::create(['lab_id' => $item->lab_id, 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'reason' => 'Lab exam', 'created_by' => $student->id]);
        AvailabilityNotifier::notify($item);
        $this->assertDatabaseCount('notifications', 0);
        $closure->delete();
        AvailabilityNotifier::notify($item);
        $student->notifications()->first()->markAsRead();
        AvailabilityNotifier::notify($item);
        $this->assertDatabaseCount('notifications', 1);
        $item->update(['status' => EquipmentStatus::UnderRepair]);
        AvailabilityNotifier::notify($item);
        $item->update(['status' => EquipmentStatus::Available]);
        AvailabilityNotifier::notify($item);
        $this->assertDatabaseCount('notifications', 2);
    }

    public function test_checkout_and_return_between_scheduler_ticks_still_notify_wishlist_subscribers(): void
    {
        [$watcher, $item] = $this->fixture(Role::Student);
        $this->actingAs($watcher)->post(route('wishlist.store', $item))->assertSessionHasNoErrors();
        $manager = User::factory()->labManager($item->lab)->create();
        $reservation = Reservation::factory()->for($item)->approved()->create();
        $this->travelTo($reservation->starts_at);
        app(ReservationService::class)->handOver($reservation, $manager);
        app(ReservationService::class)->markReturned($reservation, $manager);
        $this->assertSame(1, $watcher->notifications()->where('data->kind', 'availability')->count());
    }

    public function test_saved_items_and_notifications_are_private_and_urls_cannot_redirect_outside_the_app(): void
    {
        [$student, $item] = $this->fixture(Role::Student);
        $other = User::factory()->faculty()->create();
        $this->actingAs($other)->post(route('wishlist.store', $item));
        $this->actingAs($student)->get(route('wishlist.index'))->assertOk()->assertDontSee($item->name);
        $this->delete(route('wishlist.destroy', $item))->assertSessionHasNoErrors();
        $this->assertSame(1, $other->wishlist()->count());
        foreach (['https://outside.example/evil', '//outside.example/evil', '/\\outside.example/evil'] as $url) {
            NotificationDispatcher::once($student, $url, ['title' => 'Notice', 'url' => $url]);
        }
        $this->get(route('notifications.index'))->assertOk()->assertDontSee('href="https://outside.example', false)->assertDontSee('href="//outside.example', false)->assertDontSee('href="/\\outside.example', false);
        $this->post(route('wishlist.store', 999999))->assertNotFound();
    }

    public function test_equipment_deletion_keeps_visible_manager_and_department_audit_history(): void
    {
        [$manager, $item] = $this->fixture(Role::LabManager);
        $head = User::factory()->departmentHead($item->lab->department)->create();
        $this->actingAs($manager)->delete(route('equipment.destroy', $item))->assertSessionHasNoErrors();
        $this->get(route('activities.index'))->assertOk()->assertSee('Equipment deleted')->assertSee($item->name);
        $this->actingAs($head)->get(route('activities.index'))->assertOk()->assertSee($item->name);
        $outsider = User::factory()->departmentHead()->create();
        $this->actingAs($outsider)->get(route('activities.index'))->assertOk()->assertDontSee($item->name);
    }

    public function test_approval_and_priority_rejection_record_actor_and_reason_in_borrower_notifications(): void
    {
        [$student, $item] = $this->fixture(Role::Student);
        $faculty = User::factory()->faculty()->create();
        $manager = User::factory()->labManager($item->lab)->create();
        $start = now()->addDay();
        $pending = app(ReservationService::class)->request($student, $item, $start, $start->copy()->addHour());
        app(ReservationService::class)->request($faculty, $item, $start, $start->copy()->addHour(), true);
        $notice = $student->notifications()->where('data->dedup_key', 'reservation:'.$pending->id.':rejected')->firstOrFail();
        $this->assertStringContainsString(ReservationService::FACULTY_PRIORITY_REASON, $notice->data['message']);
        $priority = $faculty->reservations()->firstOrFail();
        app(ReservationService::class)->approve($priority, $manager);
        $this->assertDatabaseHas('activity_logs', ['event' => 'approved', 'actor_id' => $manager->id, 'reservation_id' => $priority->id]);
        $this->assertSame(1, $faculty->notifications()->where('data->dedup_key', 'reservation:'.$priority->id.':approved')->count());
        $this->actingAs($student)->get(route('activities.index'))->assertOk()->assertSee('Requested')->assertSee('Rejected');
    }
}
