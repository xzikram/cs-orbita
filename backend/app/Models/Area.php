<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Enums\AreaCategory;

class Area extends Model
{
    use HasFactory;

    protected $fillable = ['floor_id', 'code', 'name', 'category', 'description', 'is_active'];

    protected function casts(): array
    {
        return [
            'category' => AreaCategory::class,
            'is_active' => 'boolean',
        ];
    }

    public function floor(): BelongsTo
    {
        return $this->belongsTo(Floor::class);
    }

    public function building()
    {
        return $this->hasOneThrough(Building::class, Floor::class, 'id', 'id', 'floor_id', 'building_id');
    }

    public function areaObjects(): HasMany
    {
        return $this->hasMany(AreaObject::class)->orderBy('sort_order');
    }

    public function cleaningObjects()
    {
        return $this->belongsToMany(CleaningObject::class, 'area_objects')
            ->withPivot('sort_order', 'is_required')
            ->orderBy('area_objects.sort_order');
    }

    public function qrCode(): HasOne
    {
        return $this->hasOne(QrCode::class)->where('is_active', true)->latestOfMany();
    }

    public function qrCodes(): HasMany
    {
        return $this->hasMany(QrCode::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(AreaSchedule::class);
    }

    public function cleaningActivities(): HasMany
    {
        return $this->hasMany(CleaningActivity::class);
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    public function assignedUsers()
    {
        return $this->belongsToMany(User::class, 'user_areas')->withTimestamps();
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory($query, AreaCategory $category)
    {
        return $query->where('category', $category);
    }

    // Helpers
    public function getFullNameAttribute(): string
    {
        return $this->floor?->building?->name . ' - ' . $this->floor?->name . ' - ' . $this->name;
    }

    public function getTodayActivities()
    {
        return $this->cleaningActivities()->whereDate('date', today())->get();
    }

    public function isCleanedToday(?int $shiftId = null): bool
    {
        $query = $this->cleaningActivities()
            ->whereDate('date', today())
            ->where('status', 'completed');

        if ($shiftId) {
            $query->where('shift_id', $shiftId);
        }

        return $query->exists();
    }
}
