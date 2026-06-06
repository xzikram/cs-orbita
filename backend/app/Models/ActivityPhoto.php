<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Enums\PhotoType;

class ActivityPhoto extends Model
{
    protected $fillable = [
        'cleaning_activity_id', 'type', 'file_path',
        'file_size', 'original_name', 'mime_type', 'uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => PhotoType::class,
            'uploaded_at' => 'datetime',
        ];
    }

    public function cleaningActivity(): BelongsTo
    {
        return $this->belongsTo(CleaningActivity::class);
    }

    public function getUrlAttribute(): string
    {
        return asset('storage/' . $this->file_path);
    }
}
