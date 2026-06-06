<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditFinding extends Model
{
    protected $fillable = [
        'audit_score_id', 'area_id', 'category',
        'description', 'photo_path', 'status', 'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function auditScore(): BelongsTo
    {
        return $this->belongsTo(AuditScore::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }
}
