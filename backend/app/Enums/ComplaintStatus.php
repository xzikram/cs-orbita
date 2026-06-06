<?php

namespace App\Enums;

enum ComplaintStatus: string
{
    case OPEN = 'open';
    case IN_PROGRESS = 'in_progress';
    case RESOLVED = 'resolved';
    case CLOSED = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Dibuka',
            self::IN_PROGRESS => 'Diproses',
            self::RESOLVED => 'Diselesaikan',
            self::CLOSED => 'Ditutup',
        };
    }
}
