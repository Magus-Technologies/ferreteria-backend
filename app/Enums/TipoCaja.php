<?php

namespace App\Enums;

enum TipoCaja: string
{
    case CAJA_CHICA = 'CC';
    case SUB_CAJA = 'SC';

    public function label(): string
    {
        return match($this) {
            self::CAJA_CHICA => 'Caja Chica',
            self::SUB_CAJA => 'Sub-Caja',
        };
    }
}
