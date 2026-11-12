<?php

namespace Database\Factories;

use App\Enums\EquipmentStatus;
use App\Models\Document;
use App\Models\Equipment;
use App\Models\Lab;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Factories\Factory;

class EquipmentFactory extends Factory
{
    private const NAMES = [
        'Sony FX3 Camera', 'Prusa 3D Printer', 'Tektronix Oscilloscope', 'DJI Mavic Drone',
        'Rode Wireless Mic Kit', 'Arduino Starter Rig', 'Wacom Cintiq Tablet', 'Canon EOS R6',
        'Fluke Multimeter', 'Laser Cutter', 'Aputure Light Panel', 'Zoom H6 Recorder',
        'Soldering Station', 'Pottery Wheel', 'Printing Press', 'Blackmagic Pocket Cinema',
    ];

    public function definition(): array
    {
        return [
            'lab_id' => Lab::factory(),
            'name' => fake()->randomElement(self::NAMES).' #'.fake()->numberBetween(1, 99),
            'serial_number' => fake()->unique()->bothify('SN-########'),
            'qr_code' => 'EQ-'.strtoupper(fake()->unique()->bothify('????####')),
            'status' => EquipmentStatus::Available,
            'photo_path' => null,
        ];
    }

    public function underRepair(): static
    {
        return $this->state(fn () => ['status' => EquipmentStatus::UnderRepair]);
    }

    /** Currently checked out: handed over an hour ago, due back in three. */
    public function withActiveReservation(): static
    {
        return $this->state(fn () => ['status' => EquipmentStatus::Reserved])
            ->afterCreating(fn (Equipment $e) => Reservation::factory()->for($e)->activeCheckout()->create());
    }

    public function withRequiredDocuments(int $count = 2): static
    {
        return $this->has(Document::factory()->required()->count($count));
    }
}
