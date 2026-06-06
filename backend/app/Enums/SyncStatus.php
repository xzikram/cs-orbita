<?php

namespace App\Enums;

enum SyncStatus: string
{
    case PENDING = 'pending';
    case SYNCED = 'synced';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Menunggu Sinkronisasi',
            self::SYNCED => 'Tersinkronisasi',
            self::FAILED => 'Gagal Sinkronisasi',
        };
    }
}
