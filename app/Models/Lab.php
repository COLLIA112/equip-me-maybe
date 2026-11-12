<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Lab extends Model
{
    use HasFactory;

    protected $fillable = ['department_id', 'name', 'location'];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function equipment(): HasMany
    {
        return $this->hasMany(Equipment::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** Has Many Through: Lab -> Equipment -> Reservation. */
    public function reservations(): HasManyThrough
    {
        return $this->hasManyThrough(Reservation::class, Equipment::class);
    }
}
