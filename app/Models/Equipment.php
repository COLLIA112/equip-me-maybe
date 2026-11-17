<?php

namespace App\Models;

use App\Enums\EquipmentStatus;
use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Equipment extends Model
{
    use HasFactory;

    protected $table = 'equipment';

    protected $fillable = ['lab_id', 'name', 'serial_number', 'qr_code', 'status', 'photo_path'];

    protected $attributes = ['status' => 'available'];

    protected function casts(): array
    {
        return ['status' => EquipmentStatus::class];
    }

    protected static function booted(): void
    {
        // Every item gets a unique code that the QR label encodes.
        static::creating(function (Equipment $equipment) {
            if (blank($equipment->qr_code)) {
                $equipment->qr_code = self::generateCode();
            }
        });
    }

    public static function generateCode(): string
    {
        do {
            $code = 'EQ-'.Str::upper(Str::random(8));
        } while (static::where('qr_code', $code)->exists());

        return $code;
    }

    // ---------- Attribute accessors ----------
    protected function statusLabel(): Attribute
    {
        return Attribute::get(fn () => $this->status->label());
    }

    protected function photoUrl(): Attribute
    {
        return Attribute::get(fn () => $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null);
    }

    public function catalogModelName(): string
    {
        return preg_replace('/\s+#\d+$/u', '', trim($this->name));
    }

    // ---------- relationships ----------
    public function lab(): BelongsTo
    {
        return $this->belongsTo(Lab::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'equipment_tag');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function labClosures(): HasMany
    {
        return $this->hasMany(LabClosure::class, 'lab_id', 'lab_id');
    }

    public function maintenanceLogs(): HasMany
    {
        return $this->hasMany(MaintenanceLog::class);
    }

    // ---------- scopes ----------
    public function scopeFilter(Builder $query, array $f): Builder
    {
        return $query
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where(
                fn ($w) => $w->where('name', 'like', "%{$v}%")->orWhere('serial_number', 'like', "%{$v}%")->orWhere('qr_code', 'like', "%{$v}%")
            ))
            ->when($f['lab_id'] ?? null, fn ($q, $v) => $q->where('lab_id', $v))
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($f['department_id'] ?? null, fn ($q, $v) => $q->whereHas('lab', fn ($l) => $l->where('department_id', $v)))
            ->when($f['tag'] ?? null, fn ($q, $v) => $q->whereHas('tags', fn ($t) => $t->where('tags.id', $v)));
    }

    /** One aggregate query for a catalog page; future bookings do not occupy the present. */
    public function scopeWithAvailability(Builder $query): Builder
    {
        $time = now();

        return $query->withMax([
            'reservations as booked_until' => fn ($reservations) => $reservations->blocking()
                ->where('starts_at', '<=', $time)->where('ends_at', '>', $time),
            'reservations as checked_out_until' => fn ($reservations) => $reservations->checkedOut(),
            'labClosures as closure_until' => fn ($closures) => $closures
                ->where('starts_at', '<=', $time)->where('ends_at', '>', $time),
        ], 'ends_at')->withExists([
            'maintenanceLogs as has_open_repairs' => fn ($logs) => $logs->whereNull('resolved_at'),
        ]);
    }

    public function availabilityGlance(): array
    {
        if ($this->status === EquipmentStatus::UnderRepair || $this->getAttribute('has_open_repairs')) {
            return ['label' => 'Under repair', 'classes' => 'bg-red-100 text-red-800', 'available' => false];
        }
        if ($this->getAttribute('closure_until')) {
            return [
                'label' => 'Lab closed until '.Carbon::parse($this->getAttribute('closure_until'))->format('M j, Y g:i A'),
                'classes' => 'bg-orange-100 text-orange-900',
                'available' => false,
            ];
        }
        if ($this->getAttribute('checked_out_until') && Carbon::parse($this->getAttribute('checked_out_until'))->isPast()) {
            return [
                'label' => 'Checked out; overdue since '.Carbon::parse($this->getAttribute('checked_out_until'))->format('M j, Y g:i A'),
                'classes' => 'bg-orange-100 text-orange-900',
                'available' => false,
            ];
        }
        if ($this->getAttribute('booked_until') || $this->getAttribute('checked_out_until')) {
            return [
                'label' => 'Booked until '.Carbon::parse($this->getAttribute('booked_until') ?? $this->getAttribute('checked_out_until'))->format('M j, Y g:i A'),
                'classes' => 'bg-orange-100 text-orange-900',
                'available' => false,
            ];
        }

        return ['label' => 'Free now', 'classes' => 'bg-green-100 text-green-800', 'available' => true];
    }

    /** The reservation that is currently checked out, if any. */
    public function currentCheckout(): ?Reservation
    {
        return $this->reservations()
            ->where('status', ReservationStatus::CheckedOut)
            ->with('user')
            ->latest('starts_at')
            ->first();
    }
}
