<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Enums\ComplaintStatus;
use App\Enums\ComplaintPriority;

class Complaint extends Model
{
    protected $fillable = [
        'area_id', 'reporter_id', 'assignee_id',
        'title', 'category', 'description',
        'priority', 'status', 'sla_deadline', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ComplaintStatus::class,
            'priority' => ComplaintPriority::class,
            'sla_deadline' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ComplaintPhoto::class);
    }

    public function updates(): HasMany
    {
        return $this->hasMany(ComplaintUpdate::class)->orderByDesc('created_at');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', ComplaintStatus::OPEN);
    }

    public function isOverdue(): bool
    {
        return $this->sla_deadline && now()->isAfter($this->sla_deadline)
            && !in_array($this->status, [ComplaintStatus::RESOLVED, ComplaintStatus::CLOSED]);
    }
}
