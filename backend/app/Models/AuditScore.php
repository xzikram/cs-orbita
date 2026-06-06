<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AuditScore extends Model
{
    protected $fillable = [
        'cleaning_activity_id', 'auditor_id',
        'kebersihan_score', 'kerapihan_score', 'kepatuhan_sop_score',
        'total_score', 'notes', 'status', 'audited_at',
    ];

    protected $casts = [
        'audited_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (AuditScore $score) {
            $score->total_score = intval(
                ($score->kebersihan_score + $score->kerapihan_score + $score->kepatuhan_sop_score) / 3
            );
            $score->status = $score->total_score >= 80 ? 'passed' : 'failed';
        });
    }

    public function cleaningActivity(): BelongsTo
    {
        return $this->belongsTo(CleaningActivity::class);
    }

    public function auditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auditor_id');
    }

    public function findings(): HasMany
    {
        return $this->hasMany(AuditFinding::class);
    }

    public function isPassed(): bool
    {
        return $this->total_score >= 80;
    }
}
