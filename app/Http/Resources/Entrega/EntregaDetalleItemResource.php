<?php

namespace App\Http\Resources\Entrega;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Shape de un detalle (producto) dentro de una entrega */
class EntregaDetalleItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $udv      = $this->whenLoaded('unidadDerivadaVenta');
        $udiName  = optional(optional($udv)->unidadDerivadaInmutable)->name;
        $pav      = optional($udv)->productoAlmacenVenta;
        $pa       = optional($pav)->productoAlmacen;
        $producto = optional($pa)->producto;
        $ubicacion = optional($pa)->ubicacion;

        return [
            'id'                       => $this->id,
            'unidad_derivada_venta_id' => $this->unidad_derivada_venta_id,
            'cantidad'                 => (float) $this->cantidad,
            // Cuánto de ESTE detalle de entrega ya fue guiado — para calcular el
            // restante por guiar de la entrega en crear-guia.
            'cantidad_guiada'          => (float) ($this->cantidad_guiada ?? 0),
            'ubicacion'                => $this->ubicacion,
            'unidad'                   => $udiName,
            'factor'                   => $udv ? (float) $udv->factor : null,
            'cantidad_pendiente'       => $udv ? (float) $udv->cantidad_pendiente : null,
            'producto' => $producto ? [
                'id'           => $producto->id,
                'name'         => $producto->name,
                'cod_producto' => $producto->cod_producto,
                'img'          => $producto->img,
                'ubicacion_almacen' => $ubicacion?->name,
            ] : null,
        ];
    }
}
