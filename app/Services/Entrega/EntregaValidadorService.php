<?php

namespace App\Services\Entrega;

use App\Models\Entrega;
use App\Models\UnidadDerivadaInmutableVenta;
use Illuminate\Validation\ValidationException;

class EntregaValidadorService
{
    /**
     * Verifica que las cantidades solicitadas no superen lo pendiente por venta.
     *
     * @param  string  $ventaId
     * @param  array<int, array{unidad_derivada_venta_id: int, cantidad: float}>  $productos
     * @param  int|null  $excluirEntregaId  ID de entrega a ignorar (útil en updates)
     */
    public function validarCantidadesPendientes(
        string $ventaId,
        array $productos,
        ?int $excluirEntregaId = null
    ): void {
        foreach ($productos as $item) {
            $udv = UnidadDerivadaInmutableVenta::whereHas('productoAlmacenVenta', function ($q) use ($ventaId) {
                $q->where('venta_id', $ventaId);
            })->findOrFail($item['unidad_derivada_venta_id']);

            $pendiente = (float) $udv->cantidad_pendiente;

            // Si estamos editando una entrega existente, devolvemos la cantidad
            // que esa entrega ya tenía comprometida para no contar doble.
            if ($excluirEntregaId) {
                $comprometidaAntes = Entrega::where('id', $excluirEntregaId)
                    ->whereHas('detalles', function ($q) use ($udv) {
                        $q->where('unidad_derivada_venta_id', $udv->id);
                    })
                    ->join('entrega_detalle as ed', 'entrega.id', '=', 'ed.entrega_id')
                    ->where('ed.unidad_derivada_venta_id', $udv->id)
                    ->value('ed.cantidad') ?? 0;

                $pendiente += (float) $comprometidaAntes;
            }

            $solicitada = (float) $item['cantidad'];

            if ($solicitada > $pendiente + 0.001) {
                throw ValidationException::withMessages([
                    'productos' => [
                        "La cantidad solicitada ({$solicitada}) supera la cantidad pendiente ({$pendiente}) para la unidad derivada ID {$udv->id}.",
                    ],
                ]);
            }
        }
    }

    /**
     * Verifica que la entrega no esté en un estado final antes de modificarla.
     */
    public function validarNoEsFinal(Entrega $entrega): void
    {
        if ($entrega->esFinal()) {
            throw ValidationException::withMessages([
                'estado' => ["La entrega #{$entrega->id} está en estado final y no puede modificarse."],
            ]);
        }
    }

    /**
     * Verifica que el chofer no esté en mantenimiento.
     */
    public function validarChoferDisponible(string $choferId): void
    {
        $chofer = \App\Models\User::findOrFail($choferId);
        if (method_exists($chofer, 'enMantenimiento') && $chofer->enMantenimiento()) {
            throw ValidationException::withMessages([
                'chofer_id' => ["El chofer seleccionado está en período de mantenimiento."],
            ]);
        }
    }
}
