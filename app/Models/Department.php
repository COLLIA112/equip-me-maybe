<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Department extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function labs(): HasMany
    {
        return $this->hasMany(Lab::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** Has Many Through: Department -> Lab -> Equipment. */
    public function equipment(): HasManyThrough
    {
        return $this->hasManyThrough(Equipment::class, Lab::class);
    }
}
