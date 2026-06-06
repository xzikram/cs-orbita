<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CleaningObject extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'icon', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function areaObjects(): HasMany
    {
        return $this->hasMany(AreaObject::class);
    }

    public function areas()
    {
        return $this->belongsToMany(Area::class, 'area_objects')
            ->withPivot('sort_order', 'is_required');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
