<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class QrCode extends Model
{
    protected $fillable = ['area_id', 'uuid', 'code', 'qr_data', 'version', 'is_active', 'generated_at'];

    protected $casts = [
        'is_active' => 'boolean',
        'generated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (QrCode $qrCode) {
            if (empty($qrCode->uuid)) {
                $qrCode->uuid = Str::uuid()->toString();
            }
            if (empty($qrCode->generated_at)) {
                $qrCode->generated_at = now();
            }
        });
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
