<?php

namespace App\Enums;

enum TipoTransaccionCaja: string
{
    case INGRESO = 'ingreso';
    case EGRESO = 'egreso';
    case PRESTAMO_ENVIADO = 'prestamo_enviado';
    case PRESTAMO_RECIBIDO = 'prestamo_recibido';
    case MOVIMIENTO_INTERNO_SALIDA = 'movimiento_interno_salida';
    case MOVIMIENTO_INTERNO_ENTRADA = 'movimiento_interno_entrada';

    public function label(): string
    {
        return match($this) {
            self::INGRESO => 'Ingreso',
            self::EGRESO => 'Egreso',
            self::PRESTAMO_ENVIADO => 'Préstamo Enviado',
            self::PRESTAMO_RECIBIDO => 'Préstamo Recibido',
            self::MOVIMIENTO_INTERNO_SALIDA => 'Movimiento Interno - Salida',
            self::MOVIMIENTO_INTERNO_ENTRADA => 'Movimiento Interno - Entrada',
        };
    }
}
