<?php

namespace App\Enums;

enum EstadoDeCompra: string
{
    case Pendiente = 'pendiente';
    case EnProceso = 'en_proceso';
    case Completada = 'completada';
    case Anulada = 'anulada';
}
