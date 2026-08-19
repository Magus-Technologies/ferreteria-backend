<?php

namespace App\Enums;

enum TipoDocumento: string
{
    case Factura = '01';
    case Boleta = '03';
    case NotaDeVenta = 'nv';
    case Ingreso = 'in';
    case Salida = 'sa';
    case RecepcionAlmacen = 'rc';

    /**
     * Nombre para mostrarle al usuario. Los mensajes de error de caja hablan de
     * tipos de comprobante, y decir "nv" o "03" no le dice nada a quien cobra:
     * necesita leer "NOTA DE VENTA" para entender por qué su método de pago no
     * sirve para esa venta.
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::Factura => 'FACTURA',
            self::Boleta => 'BOLETA',
            self::NotaDeVenta => 'NOTA DE VENTA',
            self::Ingreso => 'INGRESO',
            self::Salida => 'SALIDA',
            self::RecepcionAlmacen => 'RECEPCIÓN DE ALMACÉN',
        };
    }

    /**
     * Etiqueta a partir del código crudo (`tipos_comprobante` de las sub-cajas
     * guarda strings, no enums). Un código desconocido se devuelve tal cual en
     * vez de reventar: el mensaje sigue siendo útil aunque sea menos bonito.
     */
    public static function etiquetaDe(string $codigo): string
    {
        return self::tryFrom($codigo)?->etiqueta() ?? strtoupper($codigo);
    }
}
