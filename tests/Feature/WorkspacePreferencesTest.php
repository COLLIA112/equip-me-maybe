<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Department;
use App\Models\Equipment;
use App\Models\Lab;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WorkspacePreferencesTest extends TestCase
{
    use RefreshDatabase;

    public static function roles(): array
    {
        return array_map(fn ($role) => [$role], Role::cases());
    }

    private function viewer(Role $role): User
    {
        $department = Department::factory()->create();
        $lab = Lab::factory()->for($department)->create();

        return User::factory()->create(['role' => $role, 'department_id' => $department->id, 'lab_id' => $role === Role::LabManager ? $lab->id : null]);
    }

    #[DataProvider('roles')]
    public function test_preferences_are_persisted_for_each_web_role_and_auditors_are_denied(Role $role): void
    {
        $user = $this->viewer($role);
        $response = $this->actingAs($user)->patch(route('preferences.update'), ['theme' => 'dark', 'remember_filters' => 1]);
        if ($role === Role::SecurityAuditor) {
            $response->assertForbidden();
            $this->assertNull($user->fresh()->preferences);
        } else {
            $response->assertRedirect(route('profile.edit'))->assertSessionHas('status', 'Your display preferences were saved.');
            $this->assertSame('dark', $user->fresh()->preferences['theme']);
            $this->assertTrue($user->fresh()->preferences['remember_filters']);
            $this->get(route('profile.edit'))->assertOk()->assertSee('data-theme="dark"', false)
                ->assertSee('data-global-search', false)->assertSee('aria-label="Breadcrumb"', false)
                ->assertSee('aria-keyshortcuts="/"', false)->assertSee('initials avatar');
        }
    }

    #[DataProvider('roles')]
    public function test_invalid_preference_input_does_not_change_a_users_settings(Role $role): void
    {
        $user = $this->viewer($role);
        $response = $this->actingAs($user)->patch(route('preferences.update'), [
            'theme' => ['dark'], 'remember_filters' => ['yes'], 'lists' => ['equipment.index' => ['q' => 'Injected']],
        ]);
        if ($role === Role::SecurityAuditor) {
            $response->assertForbidden();
        } else {
            $response->assertSessionHasErrors(['theme', 'remember_filters', 'lists']);
        }
        $this->assertNull($user->fresh()->preferences);
    }

    public function test_a_guest_cannot_change_preferences(): void
    {
        $this->patch(route('preferences.update'), ['theme' => 'dark'])->assertRedirect(route('login'));
    }

    public function test_list_filters_and_sort_are_remembered_and_clear_resets_them(): void
    {
        $user = User::factory()->student()->create();
        Equipment::factory()->create(['name' => 'Remembered Camera']);
        Equipment::factory()->create(['name' => 'Different Microscope']);
        $this->actingAs($user)->get(route('equipment.index', ['q' => 'Remembered', 'sort' => 'serial_number', 'direction' => 'desc']))
            ->assertOk()->assertSee('Remembered Camera')->assertDontSee('Different Microscope');
        $this->get(route('equipment.index'))->assertOk()->assertSee('Remembered Camera')->assertDontSee('Different Microscope')
            ->assertSee('value="Remembered"', false);
        $this->assertSame('serial_number', $user->fresh()->preferences['lists']['equipment.index']['sort']);
        $this->get(route('equipment.index', ['clear' => 1]))->assertRedirect(route('equipment.index'));
        $this->get(route('equipment.index'))->assertOk()->assertSee('Different Microscope')->assertDontSee('Clear filter');
        $this->assertSame([], $user->fresh()->preferences['lists']['equipment.index']);
    }

    public function test_preferences_do_not_leak_between_users_and_invalid_filters_are_never_stored(): void
    {
        $first = User::factory()->student()->create();
        $second = User::factory()->faculty()->create();
        Equipment::factory()->create(['name' => 'Visible Camera']);
        $this->actingAs($first)->get(route('equipment.index', ['q' => 'Unmatched']))->assertOk();
        $this->actingAs($second)->get(route('equipment.index'))->assertOk()->assertSee('Visible Camera');
        $this->get(route('equipment.index', ['q' => ['invalid'], 'status' => 'nonsense', 'sort' => 'DROP TABLE equipment', 'direction' => ['desc']]))->assertOk()->assertSee('Visible Camera');
        $this->assertSame([], $second->fresh()->preferences['lists']['equipment.index']);
        $this->assertSame('Unmatched', $first->fresh()->preferences['lists']['equipment.index']['q']);
    }

    public function test_pagination_restores_choices_and_back_links_preserve_local_filter_urls(): void
    {
        $user = User::factory()->faculty()->create();
        Equipment::factory()->count(14)->create(['name' => 'Camera']);
        $item = Equipment::firstOrFail();
        $this->actingAs($user)->get(route('equipment.index', ['q' => 'Camera', 'sort' => 'serial_number']))->assertOk();
        $this->get(route('equipment.index', ['page' => 2]))->assertOk()->assertSee('value="Camera"', false);
        $response = $this->get(route('equipment.show', $item))->assertOk()->assertSee('Back to list');
        $response->assertSee('/equipment?q=Camera&amp;sort=serial_number&amp;page=2', false);
        $this->assertArrayNotHasKey('page', $user->fresh()->preferences['lists']['equipment.index']);
    }

    public function test_disabling_remembering_clears_choices_without_resetting_theme(): void
    {
        $user = User::factory()->student()->create();
        $this->actingAs($user)->get(route('equipment.index', ['q' => 'Camera']))->assertOk();
        $this->patch(route('preferences.update'), ['theme' => 'system', 'remember_filters' => 0])->assertRedirect();
        $this->get(route('equipment.index'))->assertOk()->assertDontSee('Clear filter');
        $this->assertFalse($user->fresh()->preferences['remember_filters']);
        $this->assertArrayNotHasKey('lists', $user->fresh()->preferences);
        $this->assertSame('system', $user->fresh()->preferences['theme']);
    }

    public function test_back_links_ignore_nonlocal_session_paths(): void
    {
        $user = User::factory()->student()->create();
        $item = Equipment::factory()->create();
        $this->actingAs($user)->withSession(['list_back' => [$user->id => ['equipment.index' => '//outside.test/steal']]])
            ->get(route('equipment.show', $item))->assertOk()->assertDontSee('//outside.test/steal')
            ->assertSee('Back to list');
    }
}
