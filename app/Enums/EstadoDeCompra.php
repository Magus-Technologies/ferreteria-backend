<?php

namespace App\Enums;
// esto deberi estar
enum EstadoDeCompra: string
{
    case Pendiente = 'pendiente';
    case EnProceso = 'en_proceso';
    case Completada = 'completada';
    case Anulada = 'anulada';
}
