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
}
