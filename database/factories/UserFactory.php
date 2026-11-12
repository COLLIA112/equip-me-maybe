<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Department;
use App\Models\Lab;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => Role::Student, // public registration default
            'lab_id' => null,
            'department_id' => null,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }

    public function student(): static
    {
        return $this->state(fn () => ['role' => Role::Student]);
    }

    public function faculty(): static
    {
        return $this->state(fn () => ['role' => Role::Faculty]);
    }

    public function labManager(?Lab $lab = null): static
    {
        return $this->state(fn () => [
            'role' => Role::LabManager,
            'lab_id' => $lab?->id ?? Lab::factory(),
            'department_id' => $lab?->department_id,
        ]);
    }

    public function departmentHead(?Department $department = null): static
    {
        return $this->state(fn () => [
            'role' => Role::DepartmentHead,
            'department_id' => $department?->id ?? Department::factory(),
        ]);
    }

    public function securityAuditor(): static
    {
        return $this->state(fn () => ['role' => Role::SecurityAuditor]);
    }
}
