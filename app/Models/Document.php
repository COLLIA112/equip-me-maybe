<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Document extends Model
{
    use HasFactory;

    public const TYPES = ['safety_certificate', 'inspection', 'manual'];

    protected $fillable = ['equipment_id', 'type', 'title', 'file_path', 'original_name', 'is_required'];

    protected function casts(): array
    {
        return ['is_required' => 'boolean'];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['downloaded_at', 'acknowledged_at'])
            ->withTimestamps();
    }
}
