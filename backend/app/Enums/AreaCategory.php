<?php

namespace App\Enums;

enum AreaCategory: string
{
    case OFFICE = 'office';
    case CLINIC = 'clinic';
    case WORSHIP = 'worship';
    case SECURITY = 'security';
    case CANTEEN = 'canteen';
    case TOILET = 'toilet';
    case PHARMACY = 'pharmacy';
    case LOBBY = 'lobby';
    case CORRIDOR = 'corridor';
    case WAREHOUSE = 'warehouse';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::OFFICE => 'Kantor/Ruang Kerja',
            self::CLINIC => 'Klinik/Poli',
            self::WORSHIP => 'Tempat Ibadah',
            self::SECURITY => 'Security',
            self::CANTEEN => 'Kantin',
            self::TOILET => 'Toilet',
            self::PHARMACY => 'Farmasi',
            self::LOBBY => 'Lobby',
            self::CORRIDOR => 'Koridor',
            self::WAREHOUSE => 'Gudang',
            self::OTHER => 'Lainnya',
        };
    }
}
