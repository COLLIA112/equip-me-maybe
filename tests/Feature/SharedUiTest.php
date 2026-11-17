<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Department;
use App\Models\Document;
use App\Models\Equipment;
use App\Models\Lab;
use App\Models\MaintenanceLog;
use App\Models\Reservation;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SharedUiTest extends TestCase
{
    use RefreshDatabase;

    public static function webRoles(): array
    {
        return [
            'student' => [Role::Student, ['My Reservations', 'Required Documents'], ['Reservation Queue', 'Users and Roles', 'Laboratories']],
            'faculty' => [Role::Faculty, ['My Reservations', 'Required Documents'], ['Reservation Queue', 'Users and Roles', 'Laboratories']],
            'manager' => [Role::LabManager, ['Reservation Queue', 'Maintenance', 'Tags'], ['My Reservations', 'Users and Roles', 'Laboratories']],
            'head' => [Role::DepartmentHead, ['Department', 'Users and Roles', 'Laboratories'], ['My Reservations', 'Reservation Queue']],
        ];
    }

    #[DataProvider('webRoles')]
    public function test_sidebar_has_only_the_roles_navigation_links(Role $role, array $visible, array $hidden): void
    {
        $department = Department::factory()->create();
        $lab = Lab::factory()->for($department)->create();
        $user = User::factory()->create(['role' => $role, 'department_id' => $department->id, 'lab_id' => $role === Role::LabManager ? $lab->id : null]);

        $response = $this->actingAs($user)->get(route('equipment.index'))->assertOk()
            ->assertSee('aria-label="Main navigation"', false)
            ->assertSee('aria-controls="workspace-navigation"', false)
            ->assertSee('Skip to content')
            ->assertSee('id="main-content"', false);

        foreach ($visible as $label) {
            $response->assertSee($label);
        }
        foreach ($hidden as $label) {
            $response->assertDontSee($label);
        }
    }

    public function test_auditor_cannot_render_the_equipment_web_interface(): void
    {
        $this->actingAs(User::factory()->securityAuditor()->create())
            ->get(route('equipment.index'))->assertForbidden()
            ->assertSee('403 — Forbidden')->assertDontSee('Main navigation');
    }

    public function test_success_flash_is_accessible_dismissible_and_keeps_specific_text(): void
    {
        $this->actingAs(User::factory()->student()->create())
            ->withSession(['status' => 'Camera reservation cancelled.'])
            ->get(route('equipment.index'))->assertOk()
            ->assertSee('role="status"', false)
            ->assertSee('aria-live="polite"', false)
            ->assertSee('aria-atomic="true"', false)
            ->assertSee('data-flash', false)
            ->assertSee('data-dismiss-flash', false)
            ->assertSee('Dismiss notification')
            ->assertSee('Camera reservation cancelled.');
    }

    public function test_validation_errors_are_announced_and_do_not_auto_dismiss(): void
    {
        $errors = (new ViewErrorBag)->put('default', new MessageBag(['starts_at' => 'Choose a future start time.']));
        $this->actingAs(User::factory()->faculty()->create())
            ->withSession(['errors' => $errors])->get(route('equipment.index'))->assertOk()
            ->assertSee('role="alert"', false)
            ->assertSee('Choose a future start time.')
            ->assertDontSee('data-dismiss-flash', false);
    }

    public function test_forbidden_and_missing_pages_share_a_safe_guest_layout(): void
    {
        $this->get('/this-page-does-not-exist')->assertNotFound()
            ->assertSee('404 — Page not found')->assertSee('Back to home')
            ->assertSee('id="main-content"', false)->assertDontSee('Stack trace');
        $this->actingAs(User::factory()->student()->create())->get(route('department.index'))
            ->assertForbidden()->assertSee('403 — Forbidden')->assertSee('Back to home')
            ->assertDontSee('Stack trace');
    }

    public function test_all_reservation_lifecycle_forms_keep_post_method_spoofing_and_confirmation(): void
    {
        $lab = Lab::factory()->create();
        $item = Equipment::factory()->for($lab)->create();
        $manager = User::factory()->labManager($lab)->create();
        $borrower = User::factory()->student()->create();
        $pending = Reservation::factory()->for($item)->for($borrower, 'user')->create();
        $approved = Reservation::factory()->for($item)->approved()->create();
        $checkedOut = Reservation::factory()->for($item)->checkedOut()->create();

        $response = $this->actingAs($manager)->get(route('reservations.queue'))->assertOk();
        foreach ([['approve', $pending], ['reject', $pending], ['handover', $approved], ['return', $checkedOut]] as [$action, $reservation]) {
            $response->assertSee('action="'.route('reservations.'.$action, $reservation).'"', false);
        }
        $response->assertSee('method="POST"', false)->assertSee('name="_method" value="PATCH"', false)
            ->assertSee('name="_token"', false)->assertSee('data-confirm="Reject this request?"', false)
            ->assertSee('data-confirm="Record this item as returned?"', false);

        $this->actingAs($borrower)->get(route('reservations.index'))->assertOk()
            ->assertSee('action="'.route('reservations.cancel', $pending).'"', false)
            ->assertSee('data-confirm="Cancel this reservation?"', false)
            ->assertSee('name="_method" value="PATCH"', false);
    }

    public function test_auth_pages_keep_guest_layout_and_skip_navigation(): void
    {
        foreach (['login', 'register', 'password.request'] as $route) {
            $this->get(route($route))->assertOk()->assertSee('Skip to content')
                ->assertSee('id="main-content"', false)->assertDontSee('aria-label="Main navigation"', false);
        }
    }

    public function test_pagination_keeps_filter_links_and_disabled_controls_have_visible_names(): void
    {
        Equipment::factory()->count(13)->create(['name' => 'Camera']);
        $response = $this->actingAs(User::factory()->student()->create())
            ->get(route('equipment.index', ['q' => 'Camera']))->assertOk()
            ->assertSee('aria-label="Pagination Navigation"', false)
            ->assertSee('aria-disabled="true"', false)
            ->assertSee('Previous')
            ->assertSee('Next')
            ->assertSee('q=Camera', false);
        $this->assertDoesNotMatchRegularExpression('/<span\b[^>]*aria-label=/', $response->getContent());
    }

    public function test_other_global_confirmation_forms_keep_csrf_and_method_fields(): void
    {
        $department = Department::factory()->create();
        $lab = Lab::factory()->for($department)->create();
        $item = Equipment::factory()->for($lab)->create();
        $manager = User::factory()->labManager($lab)->create();
        $document = Document::factory()->for($item)->create();
        $log = MaintenanceLog::factory()->for($item)->unresolved()->create(['reported_by' => $manager->id]);
        $tag = Tag::factory()->create();

        $this->actingAs($manager);
        foreach ([
            ['equipment.edit', $item, 'equipment.destroy', $item, 'DELETE'],
            ['documents.index', $item, 'documents.destroy', $document, 'DELETE'],
            ['maintenance.index', null, 'maintenance.resolve', $log, 'PATCH'],
            ['tags.index', null, 'tags.destroy', $tag, 'DELETE'],
        ] as [$screen, $parameter, $action, $record, $method]) {
            $this->get(route($screen, $parameter))->assertOk()
                ->assertSee('action="'.route($action, $record).'"', false)
                ->assertSee('data-confirm=', false)
                ->assertSee('name="_method" value="'.$method.'"', false)
                ->assertSee('name="_token"', false)
                ->assertSee('action="'.route('logout').'"', false);
        }

        $head = User::factory()->departmentHead($department)->create();
        $emptyLab = Lab::factory()->for($department)->create();
        $this->actingAs($head)->get(route('department.labs.index'))->assertOk()
            ->assertSee('action="'.route('department.labs.destroy', $emptyLab).'"', false)
            ->assertSee('data-confirm="Delete this empty laboratory?"', false)
            ->assertSee('name="_method" value="DELETE"', false);
    }
}
