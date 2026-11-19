<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Lab;
use App\Models\LabClosure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LabClosureManagementTest extends TestCase
{
    use RefreshDatabase;

    public static function roles(): array
    {
        return array_map(fn ($role) => [$role], Role::cases());
    }

    private function payload(Lab $lab): array
    {
        return ['lab_id' => $lab->id, 'starts_at' => now()->addDay()->setTime(9, 0)->toDateTimeString(), 'ends_at' => now()->addDay()->setTime(12, 0)->toDateTimeString(), 'reason' => 'Scheduled electrical inspection'];
    }

    #[DataProvider('roles')]
    public function test_closure_management_is_scoped_to_managers_and_heads(Role $role): void
    {
        $lab = Lab::factory()->create();
        $user = User::factory()->create(['role' => $role, 'department_id' => $lab->department_id, 'lab_id' => $role === Role::LabManager ? $lab->id : null]);
        $this->actingAs($user);
        $payload = $this->payload($lab) + ['created_by' => 999999];
        if (in_array($role, [Role::DepartmentHead, Role::LabManager], true)) {
            $this->get(route('lab-closures.index'))->assertOk()->assertSee('Schedule a closure')->assertSee($lab->name);
            $this->post(route('lab-closures.store'), $payload)->assertSessionHasNoErrors();
            $closure = LabClosure::sole();
            $this->assertSame($user->id, $closure->created_by);
            $this->get(route('lab-closures.index'))->assertOk()->assertSee($payload['reason'])->assertSee($user->name);
            $this->patch(route('lab-closures.update', $closure), array_replace($payload, ['reason' => 'Updated inspection reason']))->assertSessionHasNoErrors();
            $this->assertSame('Updated inspection reason', $closure->fresh()->reason);
            $this->delete(route('lab-closures.destroy', $closure))->assertSessionHasNoErrors();
            $this->assertModelMissing($closure);
        } else {
            $closure = LabClosure::create($this->payload($lab) + ['created_by' => $user->id]);
            $this->get(route('lab-closures.index'))->assertForbidden();
            $this->post(route('lab-closures.store'), $payload)->assertForbidden();
            $this->patch(route('lab-closures.update', $closure), $payload)->assertForbidden();
            $this->delete(route('lab-closures.destroy', $closure))->assertForbidden();
            $this->assertModelExists($closure);
        }
    }

    public function test_managers_and_heads_cannot_access_foreign_closures(): void
    {
        $own = Lab::factory()->create(['name' => 'Own lab']);
        $foreign = Lab::factory()->create(['name' => 'Foreign secret lab']);
        $closure = LabClosure::create($this->payload($foreign) + ['created_by' => null]);
        foreach ([User::factory()->labManager($own)->create(), User::factory()->departmentHead($own->department)->create()] as $user) {
            $this->actingAs($user)->get(route('lab-closures.index'))->assertOk()->assertDontSee($foreign->name);
            $this->post(route('lab-closures.store'), $this->payload($foreign))->assertSessionHasErrors('lab_id');
            $this->patch(route('lab-closures.update', $closure), $this->payload($own))->assertForbidden();
            $this->delete(route('lab-closures.destroy', $closure))->assertForbidden();
        }
        $this->assertModelExists($closure);
    }

    public function test_manager_cannot_schedule_closures_for_another_lab_in_the_same_department(): void
    {
        $own = Lab::factory()->create();
        $other = Lab::factory()->for($own->department)->create();
        $this->actingAs(User::factory()->labManager($own)->create())->post(route('lab-closures.store'), $this->payload($other))->assertSessionHasErrors('lab_id');
        $this->assertDatabaseCount('lab_closures', 0);
        $this->actingAs(User::factory()->departmentHead($own->department)->create())->post(route('lab-closures.store'), $this->payload($other))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('lab_closures', ['lab_id' => $other->id]);
    }

    public function test_closure_rejects_invalid_dates_missing_reason_and_invalid_lab(): void
    {
        $lab = Lab::factory()->create();
        $this->actingAs(User::factory()->labManager($lab)->create());
        foreach ([
            ['starts_at' => now()->subDay()->toDateTimeString()],
            ['ends_at' => now()->toDateTimeString()],
            ['starts_at' => 'not a date'],
            ['reason' => ''],
            ['reason' => str_repeat('x', 1001)],
            ['lab_id' => 999999],
        ] as $invalid) {
            $this->post(route('lab-closures.store'), array_replace($this->payload($lab), $invalid))->assertSessionHasErrors(array_keys($invalid));
        }
        $this->assertDatabaseCount('lab_closures', 0);
    }

    public function test_overlap_scope_allows_back_to_back_and_matches_only_intersecting_windows(): void
    {
        $lab = Lab::factory()->create();
        $closure = LabClosure::create($this->payload($lab));
        $this->assertTrue(LabClosure::overlapping($closure->starts_at->copy()->addMinute(), $closure->ends_at)->exists());
        $this->assertFalse(LabClosure::overlapping($closure->ends_at, $closure->ends_at->copy()->addHour())->exists());
        $this->assertFalse(LabClosure::overlapping($closure->starts_at->copy()->subHour(), $closure->starts_at)->exists());
    }
}
