<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityItem extends Model
{
    protected $fillable = ['cleaning_activity_id', 'area_object_id', 'is_checked', 'checked_at'];

    protected $casts = [
        'is_checked' => 'boolean',
        'checked_at' => 'datetime',
    ];

    public function cleaningActivity(): BelongsTo
    {
        return $this->belongsTo(CleaningActivity::class);
    }

    public function areaObject(): BelongsTo
    {
        return $this->belongsTo(AreaObject::class);
    }
}
