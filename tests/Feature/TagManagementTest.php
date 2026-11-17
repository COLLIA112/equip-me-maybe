<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\Lab;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagManagementTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        return User::factory()->labManager(Lab::factory()->create())->create();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('tags.index'))->assertRedirect(route('login'));
    }

    public function test_students_cannot_open_tag_management(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)->get(route('tags.index'))->assertForbidden();
        $this->actingAs($student)->post(route('tags.store'), ['name' => 'x'])->assertForbidden();
    }

    public function test_lab_manager_can_list_and_create_tags(): void
    {
        $manager = $this->manager();
        Tag::create(['name' => 'existing-tag']);

        $this->actingAs($manager)->get(route('tags.index'))->assertOk()->assertSee('existing-tag');

        $this->actingAs($manager)
            ->post(route('tags.store'), ['name' => '  needs supervision  '])
            ->assertRedirect(route('tags.index'));

        $this->assertDatabaseHas('tags', ['name' => 'needs supervision']); // trimmed
    }

    public function test_tag_names_must_be_unique_and_present(): void
    {
        $manager = $this->manager();
        Tag::create(['name' => 'fragile']);

        $this->actingAs($manager)->post(route('tags.store'), ['name' => 'fragile'])->assertSessionHasErrors('name');
        $this->actingAs($manager)->post(route('tags.store'), ['name' => ''])->assertSessionHasErrors('name');
        $this->assertSame(1, Tag::where('name', 'fragile')->count());
    }

    public function test_lab_manager_cannot_rename_or_delete_shared_tags(): void
    {
        $manager = $this->manager();
        $tag = Tag::create(['name' => 'shared']);

        $this->actingAs($manager)->put(route('tags.update', $tag), ['name' => 'renamed'])->assertForbidden();
        $this->actingAs($manager)->delete(route('tags.destroy', $tag))->assertForbidden();

        $this->assertSame('shared', $tag->fresh()->name);
    }

    public function test_department_head_can_rename_a_tag(): void
    {
        $head = User::factory()->departmentHead()->create();
        $tag = Tag::create(['name' => 'old name']);

        $this->actingAs($head)
            ->put(route('tags.update', $tag), ['name' => 'new name'])
            ->assertRedirect(route('tags.index'));

        $this->assertSame('new name', $tag->fresh()->name);
    }

    public function test_renaming_a_tag_to_its_own_name_is_allowed(): void
    {
        $head = User::factory()->departmentHead()->create();
        $tag = Tag::create(['name' => 'same']);

        $this->actingAs($head)->put(route('tags.update', $tag), ['name' => 'same'])->assertSessionHasNoErrors();
    }

    public function test_department_head_can_delete_a_tag_and_it_is_detached_from_equipment(): void
    {
        $head = User::factory()->departmentHead()->create();
        $tag = Tag::create(['name' => 'obsolete']);
        $item = Equipment::factory()->create();
        $item->tags()->attach($tag);

        $this->actingAs($head)->delete(route('tags.destroy', $tag))->assertRedirect(route('tags.index'));

        $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
        $this->assertDatabaseMissing('equipment_tag', ['tag_id' => $tag->id]);
        $this->assertDatabaseHas('equipment', ['id' => $item->id]); // the equipment itself is untouched
    }
}
