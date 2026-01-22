<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Límite de Diferencia en Cierre de Caja
    |--------------------------------------------------------------------------
    |
    | Diferencia máxima permitida sin requerir supervisor (en soles)
    |
    */
    'limite_diferencia' => env('CAJA_LIMITE_DIFERENCIA', 5.00),

    /*
    |--------------------------------------------------------------------------
    | Límite Máximo de Diferencia
    |--------------------------------------------------------------------------
    |
    | Diferencia máxima absoluta permitida (en soles)
    | Si se excede, el cierre será rechazado incluso con supervisor
    |
    */
    'limite_maximo_diferencia' => env('CAJA_LIMITE_MAXIMO_DIFERENCIA', 50.00),
];
