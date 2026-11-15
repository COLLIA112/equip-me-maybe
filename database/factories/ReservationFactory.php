<?php

namespace Database\Factories;

use App\Enums\ReservationStatus;
use App\Models\Equipment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReservationFactory extends Factory
{
    public function definition(): array
    {
        $start = now()->addDays(fake()->numberBetween(1, 30))->setTime(9, 0);

        return [
            'equipment_id' => Equipment::factory(),
            'user_id' => User::factory(),
            'approved_by' => null,
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHours(4),
            'status' => ReservationStatus::Pending,
            'handed_over_at' => null,
            'returned_at' => null,
        ];
    }

    public function status(ReservationStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => ReservationStatus::Approved]);
    }

    public function checkedOut(): static
    {
        return $this->state(fn () => ['status' => ReservationStatus::CheckedOut, 'handed_over_at' => now()]);
    }

    public function returned(): static
    {
        return $this->state(fn (array $a) => [
            'status' => ReservationStatus::Returned,
            'handed_over_at' => $a['starts_at'],
            'returned_at' => $a['ends_at'],
        ]);
    }

    /** Checked out right now: started an hour ago, due back in three hours. */
    public function activeCheckout(): static
    {
        return $this->state(fn () => [
            'status' => ReservationStatus::CheckedOut,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHours(3),
            'handed_over_at' => now()->subHour(),
        ]);
    }
}
