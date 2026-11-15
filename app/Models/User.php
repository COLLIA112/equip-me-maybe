<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    // role, lab_id and department_id are NOT mass assignable: public registration can never escalate.
    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    // In memory default so a freshly registered user is already a student in this request.
    protected $attributes = ['role' => 'student'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
        ];
    }

    // ---------- role helpers ----------
    public function hasRole(Role ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isBorrower(): bool
    {
        return $this->hasRole(Role::Student, Role::Faculty);
    }

    public function managesLab(?int $labId): bool
    {
        return $this->hasRole(Role::LabManager) && $labId !== null && $this->lab_id === $labId;
    }

    public function headsDepartment(?int $departmentId): bool
    {
        return $this->hasRole(Role::DepartmentHead) && $departmentId !== null && $this->department_id === $departmentId;
    }

    // ---------- relationships ----------
    public function lab(): BelongsTo
    {
        return $this->belongsTo(Lab::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /** Many to Many with compliance data on the pivot. */
    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(Document::class)
            ->withPivot(['downloaded_at', 'acknowledged_at'])
            ->withTimestamps();
    }
}
