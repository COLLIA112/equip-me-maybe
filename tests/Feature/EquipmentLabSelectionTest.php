<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Lab;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EquipmentLabSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_department_head_must_choose_a_lab_and_gets_a_validation_message(): void
    {
        $head = User::factory()->departmentHead()->create();

        $this->actingAs($head)
            ->post(route('equipment.store'), ['name' => 'No Lab', 'serial_number' => 'SN-NOLAB-1'])
            ->assertSessionHasErrors('lab_id');

        $this->assertDatabaseMissing('equipment', ['serial_number' => 'SN-NOLAB-1']);
    }

    public function test_department_head_can_still_add_to_a_lab_in_their_department_but_not_another(): void
    {
        $department = Department::factory()->create();
        $head = User::factory()->departmentHead($department)->create();
        $ownLab = Lab::factory()->for($department)->create();
        $foreignLab = Lab::factory()->create();

        $this->actingAs($head)
            ->post(route('equipment.store'), ['lab_id' => $ownLab->id, 'name' => 'Own', 'serial_number' => 'SN-OWN-1'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('equipment', ['serial_number' => 'SN-OWN-1', 'lab_id' => $ownLab->id]);

        $this->actingAs($head)
            ->post(route('equipment.store'), ['lab_id' => $foreignLab->id, 'name' => 'Foreign', 'serial_number' => 'SN-FOR-1'])
            ->assertForbidden();
    }

    public function test_lab_manager_does_not_need_to_send_a_lab(): void
    {
        $lab = Lab::factory()->create();
        $manager = User::factory()->labManager($lab)->create();

        $this->actingAs($manager)
            ->post(route('equipment.store'), ['name' => 'Mine', 'serial_number' => 'SN-MINE-1'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('equipment', ['serial_number' => 'SN-MINE-1', 'lab_id' => $lab->id]);
    }
}
