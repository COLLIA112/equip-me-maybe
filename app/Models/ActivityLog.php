<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $fillable = ['actor_id', 'equipment_id', 'reservation_id', 'event', 'details'];

    protected function casts(): array
    {
        return ['details' => 'array'];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id')->withTrashed();
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function scopeForViewer(Builder $query, User $user): Builder
    {
        if ($user->isBorrower()) {
            return $query->where(fn ($visible) => $visible->where('actor_id', $user->id)
                ->orWhereHas('reservation', fn ($reservation) => $reservation->where('user_id', $user->id)));
        }
        if (($user->hasRole(Role::LabManager) && $user->lab_id === null)
            || ($user->hasRole(Role::DepartmentHead) && $user->department_id === null)) {
            return $query->whereRaw('1 = 0');
        }
        if ($user->hasRole(Role::LabManager)) {
            return $query->where(fn ($visible) => $visible
                ->whereHas('equipment', fn ($equipment) => $equipment->where('lab_id', $user->lab_id))
                ->orWhere('details->lab_id', $user->lab_id));
        }
        if ($user->hasRole(Role::DepartmentHead)) {
            return $query->where(fn ($visible) => $visible
                ->whereHas('equipment.lab', fn ($lab) => $lab->where('department_id', $user->department_id))
                ->orWhere('details->department_id', $user->department_id));
        }

        return $query->whereRaw('1 = 0');
    }
}
