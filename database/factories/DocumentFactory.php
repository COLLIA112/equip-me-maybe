<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\Equipment;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentFactory extends Factory
{
    public function definition(): array
    {
        $type = fake()->randomElement(Document::TYPES);

        return [
            'equipment_id' => Equipment::factory(),
            'type' => $type,
            'title' => ucfirst(str_replace('_', ' ', $type)).' '.fake()->numberBetween(1, 99),
            // Seeded demo documents all point at one placeholder PDF written by the seeder.
            'file_path' => 'documents/sample.pdf',
            'original_name' => fake()->slug(2).'.pdf',
            'is_required' => false,
        ];
    }

    public function required(): static
    {
        return $this->state(fn () => ['is_required' => true]);
    }
}
