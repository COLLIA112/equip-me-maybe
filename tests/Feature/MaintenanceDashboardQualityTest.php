<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Department;
use App\Models\Document;
use App\Models\Equipment;
use App\Models\Lab;
use App\Models\MaintenanceLog;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MaintenanceDashboardQualityTest extends TestCase
{
    use RefreshDatabase;

    public static function roles(): array
    {
        return array_map(fn (Role $role) => [$role], Role::cases());
    }

    #[DataProvider('roles')]
    public function test_maintenance_filters_keep_role_boundaries(Role $role): void
    {
        $lab = Lab::factory()->create();
        $user = User::factory()->create(['role' => $role, 'lab_id' => $lab->id, 'department_id' => $lab->department_id]);
        $item = Equipment::factory()->for($lab)->create();
        $open = MaintenanceLog::factory()->for($item)->unresolved()->create(['issue' => 'Open camera defect']);
        MaintenanceLog::factory()->for($item)->create(['issue' => 'Resolved camera defect', 'resolved_at' => now()]);
        $response = $this->actingAs($user)->get(route('maintenance.index', ['status' => 'open']));
        if (! in_array($role, [Role::LabManager, Role::DepartmentHead], true)) {
            $response->assertForbidden();

            return;
        }
        $response->assertOk()->assertSee('Open camera defect')->assertDontSee('Resolved camera defect')
            ->assertSee('data-instant-filters', false)->assertSee('onchange="this.form.requestSubmit()"', false)
            ->assertSee('Clear filter')->assertSee('Showing 1–1 of 1')->assertSee('<option value="open" selected>', false);
    }

    public function test_maintenance_ignores_invalid_values_and_hides_clear_link_without_active_filters(): void
    {
        $lab = Lab::factory()->create();
        $manager = User::factory()->labManager($lab)->create();
        $item = Equipment::factory()->for($lab)->create();
        MaintenanceLog::factory()->for($item)->unresolved()->create(['issue' => 'Open issue']);
        MaintenanceLog::factory()->for($item)->create(['issue' => 'Resolved issue', 'resolved_at' => now()]);
        $this->actingAs($manager)->get(route('maintenance.index'))->assertOk()->assertDontSee('Clear filter');
        $this->get(route('maintenance.index', ['status' => ['open'], 'sort' => 'issue; drop table equipment', 'direction' => ['desc']]))
            ->assertOk()->assertSee('Open issue')->assertSee('Resolved issue')->assertDontSee('Clear filter');
    }

    public function test_maintenance_sorting_pagination_and_contextual_empty_state(): void
    {
        $lab = Lab::factory()->create();
        $manager = User::factory()->labManager($lab)->create();
        $item = Equipment::factory()->for($lab)->create();
        MaintenanceLog::factory()->for($item)->unresolved()->create(['issue' => 'Zebra issue']);
        MaintenanceLog::factory()->for($item)->unresolved()->create(['issue' => 'Alpha issue']);
        $this->actingAs($manager)->get(route('maintenance.index', ['status' => 'open', 'sort' => 'issue', 'direction' => 'asc']))
            ->assertOk()->assertSeeInOrder(['Alpha issue', 'Zebra issue'])->assertSee('Showing 1–2 of 2');
        MaintenanceLog::factory()->count(15)->for($item)->unresolved()->create();
        $this->get(route('maintenance.index', ['status' => 'open', 'sort' => 'issue', 'direction' => 'asc']))
            ->assertOk()->assertSee('status=open', false)->assertSee('Showing 1–15 of 17');
        $this->get(route('maintenance.index', ['status' => 'resolved']))->assertOk()
            ->assertSee('No maintenance entries match these filters.')->assertSee('Clear filter');
    }

    #[DataProvider('roles')]
    public function test_role_dashboard_access_and_actions(Role $role): void
    {
        $lab = Lab::factory()->create();
        $user = User::factory()->create(['role' => $role, 'lab_id' => $lab->id, 'department_id' => $lab->department_id]);
        $response = $this->actingAs($user)->get(route('dashboard'));
        if ($role === Role::SecurityAuditor) {
            $response->assertForbidden();
        } elseif ($role === Role::DepartmentHead) {
            $response->assertRedirect(route('department.index'));
            $this->get(route('department.index'))->assertOk()->assertSee('Total reservations')->assertSee('Recorded breakdowns');
        } elseif ($role === Role::LabManager) {
            $response->assertOk()->assertSee('Needs approval')->assertSee('Due today')->assertSee('Overdue returns')
                ->assertSee('overdue=1', false)->assertDontSee('Documents still to acknowledge');
        } else {
            $response->assertOk()->assertSee('Upcoming reservations')->assertSee('Documents still to acknowledge')
                ->assertDontSee('Open reservation queue')->assertDontSee('Overdue returns');
        }
    }

    public function test_borrower_dashboard_document_progress_excludes_other_borrowers_and_acknowledged_documents(): void
    {
        $student = User::factory()->student()->create();
        $own = Reservation::factory()->for($student, 'user')->create();
        $other = Reservation::factory()->create();
        $missing = Document::factory()->for($own->equipment)->required()->create(['title' => 'Own required safety manual']);
        $done = Document::factory()->for($own->equipment)->required()->create(['title' => 'Already read manual']);
        Document::factory()->for($other->equipment)->required()->create(['title' => 'Other borrowers manual']);
        $student->documents()->attach($done, ['downloaded_at' => now(), 'acknowledged_at' => now()]);
        $this->actingAs($student)->get(route('dashboard'))->assertOk()
            ->assertSee('Own required safety manual')->assertDontSee('Already read manual')->assertDontSee('Other borrowers manual')
            ->assertSee('1 of 2 acknowledged')->assertSee('value="1" max="2"', false);
    }

    public function test_manager_dashboard_counts_due_overdue_and_pending_within_own_lab(): void
    {
        $this->travelTo(now()->setTime(12, 0));
        $lab = Lab::factory()->create();
        $manager = User::factory()->labManager($lab)->create();
        $item = Equipment::factory()->for($lab)->create();
        Reservation::factory()->for($item)->checkedOut()->create(['starts_at' => now()->subHours(2), 'ends_at' => now()->subHour()]);
        Reservation::factory()->for($item)->create();
        $foreignItem = Equipment::factory()->create(['name' => 'Foreign restricted item']);
        $foreign = Reservation::factory()->for($foreignItem)->checkedOut()->create(['ends_at' => now()->subHour()]);
        $this->actingAs($manager)->get(route('dashboard'))->assertOk()
            ->assertSee('Needs approval')->assertSee('Due today')->assertSee('Overdue returns')
            ->assertDontSee($foreign->equipment->name)
            ->assertViewHas('stats', fn ($stats) => $stats['Needs approval'] === 1 && $stats['Due today'] === 1 && $stats['Overdue returns'] === 1 && $stats['Active checkouts'] === 1);
    }

    public function test_department_report_is_scoped_for_all_roles(): void
    {
        $department = Department::factory()->create();
        $lab = Lab::factory()->for($department)->create();
        $item = Equipment::factory()->for($lab)->create(['name' => 'Own department item']);
        Equipment::factory()->create(['name' => 'Other department item']);
        foreach (Role::cases() as $role) {
            $user = User::factory()->create(['role' => $role, 'department_id' => $department->id, 'lab_id' => $lab->id]);
            $response = $this->actingAs($user)->get(route('department.index'));
            if ($role === Role::DepartmentHead) {
                $response->assertOk()->assertSee($item->name)->assertDontSee('Other department item');
            } else {
                $response->assertForbidden();
            }
        }
    }
}
