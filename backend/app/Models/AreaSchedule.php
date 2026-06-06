<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AreaSchedule extends Model
{
    protected $fillable = ['area_id', 'shift_id', 'scheduled_time', 'tolerance_minutes', 'is_active'];

    protected $casts = [
        'scheduled_time' => 'datetime:H:i',
        'is_active' => 'boolean',
    ];

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
