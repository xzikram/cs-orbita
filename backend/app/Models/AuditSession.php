<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AuditSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'audit_link_id',
        'name',
        'unit',
        'status',
        'approved_by',
        'approved_at',
        'expires_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function auditLink(): BelongsTo
    {
        return $this->belongsTo(AuditLink::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(AuditAccessLog::class);
    }
}
