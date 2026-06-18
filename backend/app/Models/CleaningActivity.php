<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use App\Enums\ActivityStatus;
use App\Enums\SyncStatus;

class CleaningActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid', 'area_id', 'user_id', 'shift_id', 'schedule_id',
        'date', 'start_time', 'end_time', 'notes',
        'status', 'sync_status', 'is_late', 'late_minutes',
        'submitted_at', 'device_id', 'approval_status', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'status' => ActivityStatus::class,
            'sync_status' => SyncStatus::class,
            'is_late' => 'boolean',
            'submitted_at' => 'datetime:Y-m-d H:i:s',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CleaningActivity $activity) {
            if (empty($activity->uuid)) {
                $activity->uuid = Str::uuid()->toString();
            }
        });
    }

    // Relationships
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(AreaSchedule::class, 'schedule_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ActivityItem::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ActivityPhoto::class);
    }

    public function auditScore(): HasOne
    {
        return $this->hasOne(AuditScore::class);
    }

    // Scopes
    public function scopeToday($query)
    {
        return $query->whereDate('date', today());
    }

    public function scopeByDate($query, $date)
    {
        return $query->whereDate('date', $date);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', ActivityStatus::COMPLETED);
    }

    public function scopeLate($query)
    {
        return $query->where('is_late', true);
    }

    public function scopePendingSync($query)
    {
        return $query->where('sync_status', SyncStatus::PENDING);
    }

    // Helpers
    public function getCompletionRate(): float
    {
        $total = $this->items()->count();
        if ($total === 0) return 0;

        $checked = $this->items()->where('is_checked', true)->count();
        return round(($checked / $total) * 100, 1);
    }
}
