<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AreaObject extends Model
{
    protected $fillable = ['area_id', 'cleaning_object_id', 'room_name', 'sort_order', 'is_required'];

    protected $casts = [
        'is_required' => 'boolean',
    ];

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function cleaningObject(): BelongsTo
    {
        return $this->belongsTo(CleaningObject::class);
    }
}
