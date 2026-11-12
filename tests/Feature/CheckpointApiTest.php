<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CheckpointApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_without_a_token_gets_401(): void
    {
        $this->getJson('/api/equipment')->assertUnauthorized();
        $this->getJson('/api/equipment/EQ-NOPE/authorization')->assertUnauthorized();
    }

    public function test_valid_token_without_the_right_ability_gets_403(): void
    {
        Sanctum::actingAs(User::factory()->securityAuditor()->create(), ['something:else']);

        $this->getJson('/api/reservations/active')->assertForbidden();
    }

    public function test_authorization_endpoint_reports_the_active_checkout(): void
    {
        $item = Equipment::factory()->withActiveReservation()->create(['qr_code' => 'EQ-TEST0001']);
        Sanctum::actingAs(User::factory()->securityAuditor()->create(), ['checkpoint:verify']);

        $this->getJson('/api/equipment/EQ-TEST0001/authorization')
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'equipment' => ['id', 'code', 'name', 'status'],
                'authorized', 'borrower', 'returns_at',
            ]])
            ->assertJsonPath('data.authorized', true)
            ->assertJsonPath('data.equipment.id', $item->id);
    }

    public function test_item_that_is_not_checked_out_is_not_authorized(): void
    {
        Equipment::factory()->create(['qr_code' => 'EQ-FREE0001']);
        Sanctum::actingAs(User::factory()->securityAuditor()->create(), ['checkpoint:verify']);

        $this->getJson('/api/equipment/EQ-FREE0001/authorization')
            ->assertOk()
            ->assertJsonPath('data.authorized', false)
            ->assertJsonPath('data.borrower', null);
    }

    public function test_equipment_list_and_active_reservations_match_the_expected_structure(): void
    {
        Equipment::factory()->count(3)->create();
        Equipment::factory()->withActiveReservation()->create();
        Sanctum::actingAs(User::factory()->securityAuditor()->create(), ['checkpoint:verify']);

        $this->getJson('/api/equipment')
            ->assertOk()
            ->assertJsonStructure(['data' => ['*' => ['id', 'code', 'name', 'serial_number', 'status', 'status_label']]]);

        $this->getJson('/api/reservations/active')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure(['data' => ['*' => ['id', 'status', 'starts_at', 'ends_at', 'borrower', 'equipment' => ['id', 'name']]]]);
    }

    public function test_only_auditors_can_log_in_for_a_token(): void
    {
        $auditor = User::factory()->securityAuditor()->create();
        $student = User::factory()->student()->create();

        $this->postJson('/api/login', ['email' => $auditor->email, 'password' => 'password'])
            ->assertOk()
            ->assertJsonStructure(['token', 'abilities']);

        $this->postJson('/api/login', ['email' => $student->email, 'password' => 'password'])
            ->assertUnprocessable();
    }
}
