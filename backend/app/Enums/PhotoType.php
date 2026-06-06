<?php

namespace App\Enums;

enum PhotoType: string
{
    case BEFORE = 'before';
    case AFTER = 'after';

    public function label(): string
    {
        return match ($this) {
            self::BEFORE => 'Sebelum',
            self::AFTER => 'Sesudah',
        };
    }
}
