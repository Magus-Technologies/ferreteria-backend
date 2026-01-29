<?php

namespace App\Enums;

enum EstadoDeCompra: string
{
    case Creado = 'cr';
    case EnEspera = 'ee';
    case Anulado = 'an';
    case Procesado = 'pr';
}
