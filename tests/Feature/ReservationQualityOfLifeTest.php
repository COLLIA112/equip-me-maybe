<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Models\Department;
use App\Models\Document;
use App\Models\Equipment;
use App\Models\Lab;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReservationQualityOfLifeTest extends TestCase
{
    use RefreshDatabase;

    public static function roles(): array
    {
        return array_combine(array_column(Role::cases(), 'value'), array_map(fn (Role $role) => [$role->value], Role::cases()));
    }

    private function fixture(string $role = 'student'): array
    {
        $department = Department::factory()->create();
        $lab = Lab::factory()->for($department)->create();
        $item = Equipment::factory()->for($lab)->create();
        $user = User::factory()->create(['role' => Role::from($role), 'department_id' => $department->id, 'lab_id' => $role === 'lab_manager' ? $lab->id : null]);

        return [$user, $item];
    }

    #[DataProvider('roles')]
    public function test_reservation_filter_pages_enforce_each_roles_access(string $role): void
    {
        [$user] = $this->fixture($role);
        $response = $this->actingAs($user)->get(route('reservations.index', ['status' => 'approved']));
        if (in_array($role, ['student', 'faculty'], true)) {
            $response->assertOk()->assertSee('data-instant-filters', false)->assertSee('onchange="this.form.requestSubmit()"', false)->assertSee('Clear filter');
        } else {
            $response->assertForbidden();
        }
        $queue = $this->get(route('reservations.queue', ['status' => 'pending', 'q' => 'borrower']));
        if ($role === 'lab_manager') {
            $queue->assertOk()->assertSee('data-filter-search', false)->assertSee('Clear filter');
        } else {
            $queue->assertForbidden();
        }
    }

    public function test_my_reservations_filters_status_keeps_selection_and_never_lists_another_borrower(): void
    {
        [$student, $item] = $this->fixture();
        Reservation::factory()->for($item)->for($student, 'user')->create(['status' => 'pending']);
        $approved = Reservation::factory()->for($item)->for($student, 'user')->approved()->create();
        Reservation::factory()->for($item)->approved()->create();
        $this->actingAs($student)->get(route('reservations.index', ['status' => 'approved']))->assertOk()
            ->assertViewHas('reservations', fn ($rows) => $rows->total() === 1 && $rows->first()->id === $approved->id)
            ->assertSee('value="approved" selected', false)->assertSee('Showing 1–1 of 1')->assertSee('Clear filter');
        $this->get(route('reservations.index', ['clear' => 1]))->assertRedirect(route('reservations.index'));
        $this->get(route('reservations.index'))->assertOk()->assertDontSee('Clear filter');
    }

    public function test_queue_filters_borrower_status_and_overdue_within_the_managers_lab(): void
    {
        [$manager, $item] = $this->fixture('lab_manager');
        $borrower = User::factory()->student()->create(['name' => 'Find Borrower', 'email' => 'find@example.test']);
        $late = Reservation::factory()->for($item)->for($borrower, 'user')->checkedOut()->create(['starts_at' => now()->subHours(3), 'ends_at' => now()->subHour()]);
        Reservation::factory()->for($item)->for($borrower, 'user')->approved()->create();
        Reservation::factory()->for($borrower, 'user')->checkedOut()->create(['ends_at' => now()->subHour()]);
        $this->actingAs($manager)->get(route('reservations.queue', ['status' => 'checked_out', 'q' => 'find@example', 'overdue' => 1]))->assertOk()
            ->assertViewHas('reservations', fn ($rows) => $rows->total() === 1 && $rows->first()->id === $late->id)
            ->assertSee('Overdue')->assertSee('Showing 1–1 of 1')->assertSee('value="find@example"', false);
    }

    public function test_invalid_filter_and_sort_values_are_ignored_instead_of_breaking_the_page(): void
    {
        [$student, $item] = $this->fixture();
        Reservation::factory()->for($item)->for($student, 'user')->create();
        $this->actingAs($student)->get(route('reservations.index', ['status' => ['approved'], 'sort' => 'starts_at desc; delete', 'direction' => ['asc']]))
            ->assertOk()->assertViewHas('reservations', fn ($rows) => $rows->total() === 1)->assertDontSee('Clear filter');
        [$manager] = $this->fixture('lab_manager');
        $this->actingAs($manager)->get(route('reservations.queue', ['q' => ['array'], 'status' => 'invalid', 'overdue' => 'invalid']))
            ->assertOk()->assertDontSee('Clear filter');
    }

    public function test_sorting_and_pagination_preserve_valid_filters(): void
    {
        [$student, $item] = $this->fixture();
        $first = Reservation::factory()->for($item)->for($student, 'user')->approved()->create(['starts_at' => now()->addDay()]);
        Reservation::factory()->count(11)->for($item)->for($student, 'user')->approved()->create(['starts_at' => now()->addDays(2)]);
        $this->actingAs($student)->get(route('reservations.index', ['status' => 'approved', 'sort' => 'starts_at', 'direction' => 'asc']))->assertOk()
            ->assertViewHas('reservations', fn ($rows) => $rows->first()->id === $first->id && $rows->total() === 12)
            ->assertSee('Showing 1–10 of 12')->assertSee('status=approved', false)->assertSee('direction=asc', false);
    }

    public function test_empty_states_distinguish_an_empty_account_from_active_filters(): void
    {
        [$student] = $this->fixture();
        $this->actingAs($student)->get(route('reservations.index'))->assertOk()->assertSee('You have no reservations yet.');
        $this->get(route('reservations.index', ['status' => 'returned']))->assertOk()->assertSee('No reservations match your filters.');
        [$manager] = $this->fixture('lab_manager');
        $this->actingAs($manager)->get(route('reservations.queue'))->assertOk()->assertSee('No open reservations for your lab.');
        $this->get(route('reservations.queue', ['q' => 'Nobody']))->assertOk()->assertSee('No reservations match your filters.');
    }

    public function test_queue_names_only_the_correct_borrowers_missing_documents_and_disables_actions(): void
    {
        [$manager, $item] = $this->fixture('lab_manager');
        $borrower = User::factory()->student()->create();
        Document::factory()->for($item)->required()->create(['title' => 'Laser safety certificate']);
        Document::factory()->for($item)->create(['title' => 'Optional brochure', 'is_required' => false]);
        $pending = Reservation::factory()->for($item)->for($borrower, 'user')->create();
        $approved = Reservation::factory()->for($item)->for($borrower, 'user')->approved()->create();
        $this->actingAs($manager)->get(route('reservations.queue'))->assertOk()->assertSee('Laser safety certificate')
            ->assertDontSee('Optional brochure')->assertSee('disabled', false)->assertSee('aria-describedby="compliance-'.$pending->id.'"', false)
            ->assertSee('aria-describedby="compliance-'.$approved->id.'"', false);
    }

    #[DataProvider('roles')]
    public function test_rejection_reason_can_only_be_saved_by_the_items_manager(string $role): void
    {
        [$user, $item] = $this->fixture($role);
        $reservation = Reservation::factory()->for($item)->create();
        $response = $this->actingAs($user)->patch(route('reservations.reject', $reservation), ['rejection_reason' => 'Needed for scheduled inspection.']);
        if ($role === 'lab_manager') {
            $response->assertRedirect()->assertSessionHas('status', 'Reservation #'.$reservation->id.' rejected.');
            $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'rejection_reason' => 'Needed for scheduled inspection.', 'rejected_by' => $user->id]);
        } else {
            $response->assertForbidden();
            $this->assertSame(ReservationStatus::Pending, $reservation->fresh()->status);
        }
    }

    public function test_invalid_rejection_reason_leaves_the_reservation_pending(): void
    {
        [$manager, $item] = $this->fixture('lab_manager');
        $reservation = Reservation::factory()->for($item)->create();
        $this->actingAs($manager)->patch(route('reservations.reject', $reservation), ['rejection_reason' => ['invalid']])->assertSessionHasErrors('rejection_reason');
        $this->patch(route('reservations.reject', $reservation), ['rejection_reason' => str_repeat('x', 1001)])->assertSessionHasErrors('rejection_reason');
        $this->assertSame(ReservationStatus::Pending, $reservation->fresh()->status);
    }

    public function test_priority_rejection_stores_a_reason_the_student_can_read(): void
    {
        [$student, $item] = $this->fixture();
        $faculty = User::factory()->faculty()->create();
        $window = ['starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour()];
        $pending = Reservation::factory()->for($item)->for($student, 'user')->create($window);
        $this->actingAs($faculty)->post(route('reservations.store', $item), $window + ['is_class_session' => 1])->assertSessionHasNoErrors();
        $pending->refresh();
        $this->assertNotEmpty($pending->rejection_reason);
        $this->actingAs($student)->get(route('reservations.show', $pending))->assertOk()->assertSee('faculty class session');
        $this->get(route('reservations.index'))->assertSee($pending->rejection_reason);
    }

    public function test_timeline_records_each_lifecycle_actor_and_exact_time(): void
    {
        [$manager, $item] = $this->fixture('lab_manager');
        $borrower = User::factory()->student()->create();
        $reservation = Reservation::factory()->for($item)->for($borrower, 'user')->create();
        $this->travelTo(now()->startOfMinute());
        $this->actingAs($manager)->patch(route('reservations.approve', $reservation))->assertSessionHasNoErrors();
        $this->assertEquals(now(), $reservation->fresh()->approved_at);
        $this->travelTo($reservation->starts_at);
        $this->patch(route('reservations.handover', $reservation))->assertSessionHasNoErrors();
        $this->patch(route('reservations.return', $reservation))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'handed_over_by' => $manager->id, 'returned_by' => $manager->id]);
        $this->actingAs($borrower)->get(route('reservations.show', $reservation))->assertOk()
            ->assertSeeInOrder(['Reservation timeline', 'Requested', $borrower->name, 'Approved', $manager->name, 'Checked out', $manager->name, 'Returned', $manager->name]);
    }

    #[DataProvider('roles')]
    public function test_timeline_and_rejection_reason_are_scoped_for_all_five_roles(string $role): void
    {
        [$user, $item] = $this->fixture($role);
        $borrower = in_array($role, ['student', 'faculty'], true) ? $user : User::factory()->student()->create();
        $reservation = Reservation::factory()->for($item)->for($borrower, 'user')->create([
            'status' => 'rejected', 'rejection_reason' => 'Reserved for an inspection.', 'rejected_at' => now(),
        ]);
        $response = $this->actingAs($user)->get(route('reservations.show', $reservation));
        if ($role === 'security_auditor') {
            $response->assertForbidden();
        } else {
            $response->assertOk()->assertSee('Reservation timeline')->assertSee('Reserved for an inspection.');
        }
        $outsider = User::factory()->student()->create();
        $this->actingAs($outsider)->get(route('reservations.show', $reservation))->assertForbidden();
    }
}
