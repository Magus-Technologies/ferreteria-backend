<?php

namespace App\Enums;

enum TipoComprobanteCaja: string
{
    case FACTURA = '01';
    case BOLETA = '03';
    case NOTA_VENTA = 'nv';
    case FACTURA_BOLETA = '01,03';
    case TODOS = 'todos';

    public function label(): string
    {
        return match($this) {
            self::FACTURA => 'Solo Facturas',
            self::BOLETA => 'Solo Boletas',
            self::NOTA_VENTA => 'Solo Notas de Venta',
            self::FACTURA_BOLETA => 'Facturas + Boletas',
            self::TODOS => 'Todos los Comprobantes',
        };
    }

    public function toArray(): array
    {
        return match($this) {
            self::FACTURA => ['01'],
            self::BOLETA => ['03'],
            self::NOTA_VENTA => ['nv'],
            self::FACTURA_BOLETA => ['01', '03'],
            self::TODOS => ['01', '03', 'nv'],
        };
    }
}
