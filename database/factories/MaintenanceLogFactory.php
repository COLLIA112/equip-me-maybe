<?php

namespace Database\Factories;

use App\Models\Equipment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MaintenanceLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'equipment_id' => Equipment::factory(),
            'reservation_id' => null,
            'reported_by' => User::factory()->securityAuditor(), // overridden by the seeder with the lab manager
            'issue' => fake()->randomElement([
                'Cracked housing after a drop', 'Battery no longer holds a charge', 'Lens will not focus',
                'Overheats during long sessions', 'Missing cable and power adapter', 'Display flickers',
                'Motor makes a grinding noise', 'Firmware update bricked the unit',
            ]),
            'resolved_at' => fake()->dateTimeBetween('-60 days', 'now'),
        ];
    }

    public function unresolved(): static
    {
        return $this->state(fn () => ['resolved_at' => null]);
    }
}
