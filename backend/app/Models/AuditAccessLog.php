<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditAccessLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'audit_session_id',
        'report_type',
        'details',
        'accessed_at',
    ];

    protected $casts = [
        'details' => 'array',
        'accessed_at' => 'datetime',
    ];

    public function auditSession(): BelongsTo
    {
        return $this->belongsTo(AuditSession::class);
    }
}
