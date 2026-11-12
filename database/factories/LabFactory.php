<?php

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

class LabFactory extends Factory
{
    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'name' => fake()->unique()->words(2, true).' Lab',
            'location' => 'Room '.fake()->numberBetween(100, 499),
        ];
    }
}
