<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Equipment;
use App\Models\Lab;
use App\Models\MaintenanceLog;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DepartmentReportExtrasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 15)->setTime(12, 0));
    }

    public static function roles(): array
    {
        return array_map(fn (Role $role) => [$role], Role::cases());
    }

    #[DataProvider('roles')]
    public function test_only_heads_can_export_and_exports_are_department_scoped(Role $role): void
    {
        $lab = Lab::factory()->create();
        $user = User::factory()->create(['role' => $role, 'lab_id' => $lab->id, 'department_id' => $lab->department_id]);
        Equipment::factory()->for($lab)->create(['name' => '=Own camera']);
        Equipment::factory()->create(['name' => 'Outside camera']);
        $response = $this->actingAs($user)->get('/department/export?type=usage&format=csv');
        if ($role !== Role::DepartmentHead) {
            $response->assertForbidden();

            return;
        }
        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $body = $response->streamedContent();
        $this->assertStringContainsString("'=Own camera", $body);
        $this->assertStringNotContainsString('Outside camera', $body);
        $this->get('/department/export?type=usage&format=print')->assertOk()->assertSee('Print report')->assertSee('Own camera')->assertDontSee('Outside camera');
    }

    public function test_custom_range_clips_and_unions_bookings_for_utilization_and_filters_breakdowns(): void
    {
        $lab = Lab::factory()->create();
        $head = User::factory()->departmentHead($lab->department)->create();
        $item = Equipment::factory()->for($lab)->create(['name' => 'Clipped camera']);
        foreach ([['2026-11-09 20:00', '2026-11-10 12:00'], ['2026-11-10 06:00', '2026-11-10 18:00']] as [$start, $end]) {
            Reservation::factory()->for($item)->approved()->create(['starts_at' => $start, 'ends_at' => $end]);
        }
        Reservation::factory()->for($item)->create(['starts_at' => '2026-11-10 18:00', 'ends_at' => '2026-11-10 23:00']);
        MaintenanceLog::factory()->for($item)->create(['issue' => 'Inside date range', 'created_at' => '2026-11-10 08:00', 'repair_cost' => '25.50']);
        MaintenanceLog::factory()->for($item)->create(['issue' => 'Outside date range', 'created_at' => '2026-10-10 08:00', 'repair_cost' => '100.00']);
        $response = $this->actingAs($head)->get('/department?period=custom&from=2026-11-10&to=2026-11-10');
        $response->assertOk()->assertSee('75.0%')->assertSee('18.0 booked hours')->assertSee('Inside date range')->assertDontSee('Outside date range')
            ->assertSee('25.50')->assertSee('Breakdown trend')->assertSee('24-hour capacity')->assertSee('Most reserved equipment');
        $response->assertViewHas('reservationTotal', 3)->assertViewHas('breakdownTotal', 1);
        $csv = $this->get('/department/export?type=breakdowns&format=csv&period=custom&from=2026-11-10&to=2026-11-10')->assertOk()->streamedContent();
        $this->assertStringContainsString('Inside date range', $csv);
        $this->assertStringNotContainsString('Outside date range', $csv);
    }

    public function test_invalid_or_reversed_ranges_and_array_inputs_use_safe_default(): void
    {
        $head = User::factory()->departmentHead()->create();
        foreach ([['period' => 'custom', 'from' => '2026-11-20', 'to' => '2026-11-01'], ['period' => ['week'], 'from' => ['x'], 'to' => 'bad'], ['period' => 'custom', 'from' => '2000-01-01', 'to' => '2026-11-15']] as $query) {
            $this->actingAs($head)->get(route('department.index', $query))->assertOk()
                ->assertViewHas('range', fn ($range) => $range['period'] === 'month' && $range['from'] === '2026-11-01' && $range['to'] === '2026-11-30');
        }
        $this->get('/department?period=semester')->assertOk()->assertViewHas('range', fn ($range) => $range['from'] === '2026-07-01' && $range['to'] === '2026-12-31');
        $this->get('/department?period=week')->assertOk()->assertViewHas('range', fn ($range) => $range['from'] === '2026-11-09' && $range['to'] === '2026-11-15');
        $this->get('/department/export?type=users&format=csv')->assertUnprocessable();
    }

    #[DataProvider('roles')]
    public function test_repair_cost_capture_obeys_existing_role_and_scope_rules(Role $role): void
    {
        $lab = Lab::factory()->create();
        $user = User::factory()->create(['role' => $role, 'lab_id' => $lab->id, 'department_id' => $lab->department_id]);
        $item = Equipment::factory()->for($lab)->create();
        $response = $this->actingAs($user)->post(route('maintenance.store'), ['equipment_id' => $item->id, 'issue' => 'Cracked battery', 'repair_cost' => '1234.50']);
        if (in_array($role, [Role::DepartmentHead, Role::LabManager], true)) {
            $response->assertSessionHasNoErrors();
            $this->assertDatabaseHas('maintenance_logs', ['equipment_id' => $item->id, 'repair_cost' => '1234.50']);
            $this->get(route('maintenance.index'))->assertOk()->assertSee('1,234.50');
        } else {
            $response->assertForbidden();
            $this->assertDatabaseCount('maintenance_logs', 0);
        }
    }

    public function test_invalid_repair_costs_are_rejected_and_cross_lab_costs_cannot_be_written(): void
    {
        $lab = Lab::factory()->create();
        $manager = User::factory()->labManager($lab)->create();
        $item = Equipment::factory()->for($lab)->create();
        foreach (['-1', '10000000000.00', '1.234', 'unknown', ['2']] as $cost) {
            $this->actingAs($manager)->post(route('maintenance.store'), ['equipment_id' => $item->id, 'issue' => 'Cost validation', 'repair_cost' => $cost])->assertSessionHasErrors('repair_cost');
        }
        $foreign = Equipment::factory()->create();
        $this->post(route('maintenance.store'), ['equipment_id' => $foreign->id, 'issue' => 'Outside cost', 'repair_cost' => '1.00'])->assertForbidden();
        $this->assertDatabaseCount('maintenance_logs', 0);
    }

    public function test_breakdown_csv_escapes_formula_fields_and_shows_cost_and_borrower(): void
    {
        $lab = Lab::factory()->create();
        $head = User::factory()->departmentHead($lab->department)->create();
        $item = Equipment::factory()->for($lab)->create();
        $borrower = User::factory()->student()->create(['name' => '+Borrower formula']);
        $reservation = Reservation::factory()->for($item)->for($borrower, 'user')->returned()->create();
        MaintenanceLog::factory()->for($item)->create(['issue' => "\t=HYPERLINK(\"x\")", 'reservation_id' => $reservation->id, 'repair_cost' => '77.25']);
        $body = $this->actingAs($head)->get('/department/export?type=breakdowns&format=csv')->assertOk()->streamedContent();
        $this->assertStringContainsString("'\t=HYPERLINK", $body);
        $this->assertStringContainsString("'+Borrower formula", $body);
        $this->assertStringContainsString('77.25', $body);
    }
}
