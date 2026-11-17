<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Department;
use App\Models\Equipment;
use App\Models\Lab;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdministrationManagementTest extends TestCase
{
    use RefreshDatabase;

    public static function roles(): array
    {
        return array_map(fn ($role) => [$role], Role::cases());
    }

    #[DataProvider('roles')]
    public function test_laboratory_administration_is_department_head_only(Role $role): void
    {
        $department = Department::factory()->create();
        $lab = Lab::factory()->for($department)->create();
        $user = User::factory()->create(['role' => $role, 'department_id' => $department->id, 'lab_id' => $role === Role::LabManager ? $lab->id : null]);
        $this->actingAs($user);
        if ($role === Role::DepartmentHead) {
            $this->get(route('department.labs.index'))->assertOk()->assertSee($lab->name)->assertSee('Users and roles');
            $this->post(route('department.labs.store'), ['name' => 'New scoped lab', 'location' => 'Room 1', 'department_id' => 99999])->assertSessionHasNoErrors();
            $created = Lab::whereName('New scoped lab')->sole();
            $this->assertSame($department->id, $created->department_id);
            $this->patch(route('department.labs.update', $created), ['name' => 'Renamed lab'])->assertSessionHasNoErrors();
            $this->delete(route('department.labs.destroy', $created))->assertSessionHasNoErrors();
            $this->assertModelMissing($created);
        } else {
            $this->get(route('department.labs.index'))->assertForbidden();
            $this->post(route('department.labs.store'), ['name' => 'Forbidden lab'])->assertForbidden();
            $this->patch(route('department.labs.update', $lab), ['name' => 'Forbidden rename'])->assertForbidden();
            $this->delete(route('department.labs.destroy', $lab))->assertForbidden();
        }
    }

    #[DataProvider('roles')]
    public function test_tag_administration_requires_a_manager_or_department_head(Role $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $tag = Tag::factory()->create(['name' => 'Existing shared tag']);
        $this->actingAs($user);
        if (in_array($role, [Role::DepartmentHead, Role::LabManager], true)) {
            $this->get(route('tags.index'))->assertOk()->assertSee($tag->name)->assertSee('Tags are shared across departments.');
            $this->post(route('tags.store'), ['name' => 'New shared tag'])->assertSessionHasNoErrors();
            $created = Tag::whereName('New shared tag')->sole();
            $this->patch(route('tags.update', $created), ['name' => 'Renamed shared tag'])->assertSessionHasNoErrors();
            $this->delete(route('tags.destroy', $created))->assertSessionHasNoErrors();
            $this->assertModelMissing($created);
        } else {
            $this->get(route('tags.index'))->assertForbidden();
            $this->post(route('tags.store'), ['name' => 'Forbidden tag'])->assertForbidden();
            $this->patch(route('tags.update', $tag), ['name' => 'Forbidden tag'])->assertForbidden();
            $this->delete(route('tags.destroy', $tag))->assertForbidden();
        }
    }

    public function test_head_cannot_list_edit_or_delete_laboratories_from_another_department(): void
    {
        $department = Department::factory()->create();
        $head = User::factory()->departmentHead($department)->create();
        $own = Lab::factory()->for($department)->create(['name' => 'Own lab']);
        $foreign = Lab::factory()->create(['name' => 'Foreign laboratory']);
        $this->actingAs($head)->get(route('department.labs.index'))->assertOk()->assertSee($own->name)->assertDontSee($foreign->name);
        $this->patch(route('department.labs.update', $foreign), ['name' => 'Attack'])->assertForbidden();
        $this->delete(route('department.labs.destroy', $foreign))->assertForbidden();
        $this->assertSame('Foreign laboratory', $foreign->fresh()->name);
    }

    public function test_used_labs_and_tags_cannot_be_deleted_and_show_a_reason(): void
    {
        $lab = Lab::factory()->create();
        $head = User::factory()->departmentHead($lab->department)->create();
        $equipment = Equipment::factory()->for($lab)->create();
        $tag = Tag::factory()->create();
        $equipment->tags()->attach($tag);
        $this->actingAs($head)->get(route('department.labs.index'))->assertSee('Deletion unavailable: this laboratory has equipment or assigned users.');
        $this->delete(route('department.labs.destroy', $lab))->assertSessionHasErrors('lab');
        $this->get(route('tags.index'))->assertSee('Deletion unavailable: this tag is assigned to equipment.');
        $this->delete(route('tags.destroy', $tag))->assertSessionHasErrors('tag');
        $this->assertModelExists($lab);
        $this->assertModelExists($tag);
        $empty = Lab::factory()->for($lab->department)->create();
        User::factory()->labManager($empty)->create();
        $this->delete(route('department.labs.destroy', $empty))->assertSessionHasErrors('lab');
        $this->assertModelExists($empty);
    }

    public function test_admin_names_validate_required_size_and_duplicates(): void
    {
        $lab = Lab::factory()->create();
        $head = User::factory()->departmentHead($lab->department)->create();
        $tag = Tag::factory()->create();
        $this->actingAs($head);
        foreach (['', str_repeat('a', 151), $lab->name] as $name) {
            $this->post(route('department.labs.store'), ['name' => $name])->assertSessionHasErrors('name');
        }
        foreach (['', str_repeat('a', 101), $tag->name] as $name) {
            $this->post(route('tags.store'), ['name' => $name])->assertSessionHasErrors('name');
        }
        $this->patch(route('department.labs.update', $lab), ['name' => $lab->name, 'location' => str_repeat('x', 256)])->assertSessionHasErrors('location');
    }
}
