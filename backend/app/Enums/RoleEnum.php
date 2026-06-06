<?php

namespace App\Enums;

enum RoleEnum: string
{
    case CLEANING_SERVICE = 'cleaning_service';
    case SUPERVISOR = 'supervisor';
    case KEPALA_RUANGAN = 'kepala_ruangan';
    case ADMINISTRATOR = 'administrator';
    case MANAJEMEN = 'manajemen';

    public function label(): string
    {
        return match ($this) {
            self::CLEANING_SERVICE => 'Cleaning Service',
            self::SUPERVISOR => 'Supervisor',
            self::KEPALA_RUANGAN => 'Kepala Ruangan',
            self::ADMINISTRATOR => 'Administrator',
            self::MANAJEMEN => 'Manajemen',
        };
    }
}
