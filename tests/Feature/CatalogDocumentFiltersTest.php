<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Document;
use App\Models\Equipment;
use App\Models\Lab;
use App\Models\MaintenanceLog;
use App\Models\Reservation;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CatalogDocumentFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_malformed_catalog_filters_are_ignored_without_hiding_equipment(): void
    {
        $item = Equipment::factory()->create(['name' => 'Visible camera']);
        $this->actingAs(User::factory()->student()->create())
            ->get(route('equipment.index', ['q' => ['bad'], 'status' => ['available'], 'lab_id' => 'bad', 'sort' => ['bad']]))
            ->assertOk()->assertSee($item->name);
    }

    public function test_head_does_not_see_a_document_manager_link_they_cannot_open(): void
    {
        $item = Equipment::factory()->create();
        $head = User::factory()->departmentHead($item->lab->department)->create();
        $this->actingAs($head)->get(route('equipment.show', $item))->assertOk()
            ->assertDontSee('href="'.route('documents.index', $item).'"', false);
        $this->get(route('documents.index', $item))->assertForbidden();
    }

    public function test_equipment_with_history_cannot_be_deleted(): void
    {
        $item = Equipment::factory()->create();
        $manager = User::factory()->labManager($item->lab)->create();
        $reservation = Reservation::factory()->for($item)->returned()->create();
        $this->actingAs($manager)->delete(route('equipment.destroy', $item))
            ->assertSessionHasErrors('equipment');
        $this->assertDatabaseHas('equipment', ['id' => $item->id]);
        $this->assertDatabaseHas('reservations', ['id' => $reservation->id]);
    }

    public static function roles(): array
    {
        return [
            'student' => ['student', true, false, true],
            'faculty' => ['faculty', true, false, true],
            'manager' => ['lab_manager', true, true, false],
            'head' => ['department_head', true, false, false],
            'auditor' => ['security_auditor', false, false, false],
        ];
    }

    private function userForRole(string $role, Lab $lab): User
    {
        return User::factory()->create([
            'role' => Role::from($role),
            'department_id' => $lab->department_id,
            'lab_id' => $role === 'lab_manager' ? $lab->id : null,
        ]);
    }

    #[DataProvider('roles')]
    public function test_catalog_filters_and_management_visibility_for_each_role(string $role, bool $catalog, bool $manager, bool $borrower): void
    {
        $item = Equipment::factory()->create(['name' => 'Matched camera', 'serial_number' => 'MATCH-SERIAL']);
        $tag = Tag::factory()->create();
        $item->tags()->attach($tag);
        Equipment::factory()->for($item->lab)->create(['name' => 'Hidden printer']);
        $user = $this->userForRole($role, $item->lab);
        $response = $this->actingAs($user)->get(route('equipment.index', [
            'q' => 'MATCH-SERIAL', 'status' => 'available', 'lab_id' => $item->lab_id,
            'department_id' => $item->lab->department_id, 'tag' => $tag->id,
        ]));
        if (! $catalog) {
            $response->assertForbidden();

            return;
        }
        $response->assertOk()->assertSee('Matched camera')->assertDontSee('Hidden printer')
            ->assertSee('data-instant-filters', false)->assertSee('data-filter-search', false)
            ->assertSee('onchange="this.form.requestSubmit()"', false)->assertSee('Clear filter')
            ->assertSee('Showing 1–1 of 1')->assertSee('value="MATCH-SERIAL"', false);
        if ($role === 'lab_manager' || $role === 'department_head') {
            $response->assertSee('Add equipment');
        } else {
            $response->assertDontSee('Add equipment');
        }
    }

    #[DataProvider('roles')]
    public function test_document_manager_and_checklist_filters_enforce_each_role(string $role, bool $catalog, bool $manager, bool $borrower): void
    {
        $item = Equipment::factory()->create();
        $user = $this->userForRole($role, $item->lab);
        $doc = Document::factory()->for($item)->required()->create(['title' => 'Required safety']);
        Reservation::factory()->for($item)->for($user, 'user')->create();
        $this->actingAs($user);
        $response = $this->get(route('documents.index', [$item, 'required' => 'required']));
        $manager ? $response->assertOk()->assertSee($doc->title)->assertSee('Clear filter') : $response->assertForbidden();
        $response = $this->get(route('documents.checklist', ['acknowledged' => 'no']));
        $borrower ? $response->assertOk()->assertSee($doc->title)->assertSee('Clear filter') : $response->assertForbidden();
    }

    public function test_catalog_sorting_pagination_and_contextual_empty_states(): void
    {
        $lab = Lab::factory()->create();
        $student = User::factory()->student()->create();
        for ($i = 1; $i <= 14; $i++) {
            Equipment::factory()->for($lab)->create(['name' => sprintf('Camera %02d', $i)]);
        }
        $this->actingAs($student)->get(route('equipment.index'))->assertOk()
            ->assertDontSee('Clear filter')->assertSee('Showing 1–12 of 14');
        $this->get(route('equipment.index', ['q' => 'Camera', 'sort' => 'name', 'direction' => 'desc']))
            ->assertOk()->assertSeeInOrder(['Camera 14', 'Camera 13'])
            ->assertSee('sort=name', false)->assertSee('direction=desc', false)->assertSee('q=Camera', false);
        $this->get(route('equipment.index', ['q' => 'No match']))->assertOk()
            ->assertSee('No equipment matches your filters.')->assertSee('Showing 0–0 of 0');
        $this->get(route('equipment.index', ['sort' => 'name desc; DROP TABLE equipment', 'direction' => 'wrong']))
            ->assertOk()->assertSeeInOrder(['Camera 01', 'Camera 02']);
    }

    public function test_catalog_invalid_scalar_filters_are_ignored_and_selected_values_persist(): void
    {
        $item = Equipment::factory()->create();
        $this->actingAs(User::factory()->student()->create())
            ->get(route('equipment.index', ['status' => 'invalid', 'tag' => 999999, 'department_id' => 999999, 'page' => ['bad']]))
            ->assertOk()->assertSee($item->name)->assertDontSee('Clear filter');
        $this->get(route('equipment.index', ['status' => 'available']))->assertOk()
            ->assertSee('value="available" selected', false);
    }

    public function test_document_filters_sort_paginate_and_ignore_invalid_values(): void
    {
        $item = Equipment::factory()->create();
        $manager = User::factory()->labManager($item->lab)->create();
        $borrower = User::factory()->student()->create();
        for ($i = 1; $i <= 12; $i++) {
            $doc = Document::factory()->for($item)->required()->create(['title' => sprintf('Safety %02d', $i)]);
            $borrower->documents()->attach($doc, ['downloaded_at' => now(), 'acknowledged_at' => now()]);
        }
        Document::factory()->for($item)->create(['title' => 'Optional manual', 'is_required' => false]);
        $this->actingAs($manager)->get(route('documents.index', $item))->assertOk()
            ->assertDontSee('Clear filter')->assertSee('Showing 1–10 of 13');
        $this->get(route('documents.index', [$item, 'required' => 'required', 'acknowledged' => 'yes', 'sort' => 'title', 'direction' => 'desc']))
            ->assertOk()->assertSeeInOrder(['Safety 12', 'Safety 11'])->assertDontSee('Optional manual')
            ->assertSee('Showing 1–10 of 12')->assertSee('required=required', false)->assertSee('acknowledged=yes', false);
        $this->get(route('documents.index', [$item, 'required' => ['bad'], 'acknowledged' => 'invalid', 'sort' => 'unsafe']))
            ->assertOk()->assertSee('Showing 1–10 of 13')->assertDontSee('Clear filter');
        $this->get(route('documents.index', [$item, 'required' => 'optional', 'acknowledged' => 'yes']))
            ->assertOk()->assertSee('No documents match your filters.')->assertSee('Showing 0–0 of 0');
    }

    public function test_checklist_acknowledgement_is_personal_and_required_filter_preserves_scope(): void
    {
        $item = Equipment::factory()->create();
        $student = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        $required = Document::factory()->for($item)->required()->create(['title' => 'Required personal safety']);
        $optional = Document::factory()->for($item)->create(['title' => 'Optional personal manual', 'is_required' => false]);
        $outside = Document::factory()->required()->create(['title' => 'Unrelated safety']);
        Reservation::factory()->for($item)->for($student, 'user')->create();
        $other->documents()->attach($required, ['downloaded_at' => now(), 'acknowledged_at' => now()]);
        $this->actingAs($student)->get(route('documents.checklist'))->assertOk()
            ->assertSee($required->title)->assertDontSee($optional->title)->assertDontSee($outside->title)->assertDontSee('Clear filter');
        $this->get(route('documents.checklist', ['acknowledged' => 'no']))->assertOk()->assertSee($required->title);
        $this->get(route('documents.checklist', ['acknowledged' => 'yes']))->assertOk()->assertDontSee($required->title)
            ->assertSee('No documents match your filters for your open reservations.');
        $this->get(route('documents.checklist', ['required' => 'optional']))->assertOk()->assertSee($optional->title)->assertDontSee($required->title);
        $this->get(route('documents.checklist', ['required' => ['bad'], 'acknowledged' => ['bad'], 'direction' => ['bad']]))
            ->assertOk()->assertSee($required->title)->assertDontSee('Clear filter');
    }

    public function test_equipment_with_maintenance_history_cannot_be_deleted_but_unused_equipment_can(): void
    {
        $item = Equipment::factory()->create();
        $manager = User::factory()->labManager($item->lab)->create();
        $log = MaintenanceLog::factory()->for($item)->create(['reported_by' => $manager->id]);
        $this->actingAs($manager)->delete(route('equipment.destroy', $item))->assertSessionHasErrors('equipment');
        $this->assertDatabaseHas('maintenance_logs', ['id' => $log->id]);
        $unused = Equipment::factory()->for($item->lab)->create();
        $this->delete(route('equipment.destroy', $unused))->assertRedirect(route('equipment.index'));
        $this->assertDatabaseMissing('equipment', ['id' => $unused->id]);
    }

    #[DataProvider('roles')]
    public function test_delete_preserves_history_and_enforces_role_permissions(string $role, bool $catalog, bool $manager, bool $borrower): void
    {
        $item = Equipment::factory()->create();
        $reservation = Reservation::factory()->for($item)->returned()->create();
        $user = $this->userForRole($role, $item->lab);
        $response = $this->actingAs($user)->delete(route('equipment.destroy', $item));
        if ($manager || $role === 'department_head') {
            $response->assertSessionHasErrors('equipment');
        } else {
            $response->assertForbidden();
        }
        $this->assertDatabaseHas('equipment', ['id' => $item->id]);
        $this->assertDatabaseHas('reservations', ['id' => $reservation->id]);
    }

    public function test_unfiltered_empty_catalog_explains_missing_inventory(): void
    {
        $this->actingAs(User::factory()->student()->create())->get(route('equipment.index'))
            ->assertOk()->assertSee('No equipment has been added yet.')->assertSee('Showing 0–0 of 0')->assertDontSee('Clear filter');
    }
}
