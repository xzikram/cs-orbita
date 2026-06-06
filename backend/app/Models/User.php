<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Enums\RoleEnum;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'username',
        'password',
        'employee_id',
        'phone',
        'role',
        'avatar',
        'is_active',
        'device_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => RoleEnum::class,
            'is_active' => 'boolean',
        ];
    }

    // Relationships
    public function areas()
    {
        return $this->belongsToMany(Area::class, 'user_areas')->withTimestamps();
    }

    public function cleaningActivities(): HasMany
    {
        return $this->hasMany(CleaningActivity::class);
    }

    public function auditScores(): HasMany
    {
        return $this->hasMany(AuditScore::class, 'auditor_id');
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class, 'reporter_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function loginHistories(): HasMany
    {
        return $this->hasMany(LoginHistory::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByRole($query, RoleEnum $role)
    {
        return $query->where('role', $role);
    }

    // Helpers
    public function isCleaningService(): bool
    {
        return $this->role === RoleEnum::CLEANING_SERVICE;
    }

    public function isSupervisor(): bool
    {
        return $this->role === RoleEnum::SUPERVISOR;
    }

    public function isKepalaRuangan(): bool
    {
        return $this->role === RoleEnum::KEPALA_RUANGAN;
    }

    public function isAdmin(): bool
    {
        return $this->role === RoleEnum::ADMINISTRATOR;
    }

    public function isManajemen(): bool
    {
        return $this->role === RoleEnum::MANAJEMEN;
    }

    public function hasAccessToArea(int $areaId): bool
    {
        if ($this->isAdmin() || $this->isSupervisor() || $this->isManajemen()) {
            return true;
        }

        return $this->areas()->where('areas.id', $areaId)->exists();
    }
}
