<?php

namespace App\Helpers;

class Formato
{
    /**
     * Formatea una cantidad para documentos impresos conservando medias medidas.
     *
     * Antes las plantillas usaban number_format($cantidad, 0), que redondea:
     * una venta de 2.5 se imprimia como "3". Este helper solo omite decimales
     * cuando la cantidad es realmente entera, asi el ticket no miente.
     *
     *   2       -> "2"
     *   2.5     -> "2.5"
     *   2.25    -> "2.25"
     *   2.500   -> "2.5"      (sin ceros de relleno)
     *   1234.5  -> "1,234.5"  (mantiene separador de miles)
     *
     * @param mixed $valor        Cantidad (float, int o string decimal de la DB).
     * @param int   $maxDecimales Precision maxima; 3 = precision de la columna
     *                            unidadderivadainmutableventa.cantidad decimal(9,3).
     */
    public static function cantidad($valor, int $maxDecimales = 3): string
    {
        $numero = round((float) $valor, $maxDecimales);

        // Entera: se imprime igual que antes, sin ".00" de relleno.
        if ($numero == (int) $numero) {
            return number_format($numero, 0);
        }

        // Fraccionaria: mostrar decimales y recortar los ceros sobrantes.
        return rtrim(rtrim(number_format($numero, $maxDecimales), '0'), '.');
    }
}
