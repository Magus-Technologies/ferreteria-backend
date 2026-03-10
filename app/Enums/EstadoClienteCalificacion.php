<?php

namespace App\Enums;

enum EstadoClienteCalificacion: string
{
    case EXCELENTE = 'excelente';
    case BUENO = 'bueno';
    case REGULAR = 'regular';
    case PROBLEMATICO = 'problematico';

    public function label(): string
    {
        return match($this) {
            self::EXCELENTE => 'Excelente',
            self::BUENO => 'Bueno',
            self::REGULAR => 'Regular',
            self::PROBLEMATICO => 'Problemático',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::EXCELENTE => 'green',
            self::BUENO => 'blue',
            self::REGULAR => 'orange',
            self::PROBLEMATICO => 'red',
        };
    }
}
