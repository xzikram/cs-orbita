<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Floor extends Model
{
    use HasFactory;

    protected $fillable = ['building_id', 'name', 'level_number', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function areas(): HasMany
    {
        return $this->hasMany(Area::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
