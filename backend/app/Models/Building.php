<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Building extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'address', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function floors(): HasMany
    {
        return $this->hasMany(Floor::class);
    }

    public function areas()
    {
        return $this->hasManyThrough(Area::class, Floor::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
