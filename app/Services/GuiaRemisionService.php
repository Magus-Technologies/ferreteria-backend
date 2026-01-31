<?php

namespace App\Services;

use App\Models\GuiaRemision;
use App\Models\DetalleGuiaRemision;
use App\Models\ProductoAlmacen;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GuiaRemisionService
{
    /**
     * Crear una nueva guía de remisión con sus detalles
     */
    public function crear(array $data): GuiaRemision
    {
        return DB::transaction(function () use ($data) {
            // Generar ULID para la guía
            $guiaId = (string) Str::ulid();

            // Crear guía de remisión
            $guia = GuiaRemision::create([
                'id' => $guiaId,
                'venta_id' => $data['venta_id'] ?? null,
                'tipo_guia' => $data['tipo_guia'],
                'serie' => $data['serie'] ?? null,
                'numero' => $data['numero'] ?? null,
                'fecha_emision' => $data['fecha_emision'],
                'fecha_traslado' => $data['fecha_traslado'],
                'afecta_stock' => $data['afecta_stock'],
                'cliente_id' => $data['cliente_id'] ?? null,
                'motivo_traslado_id' => $data['motivo_traslado_id'],
                'modalidad_transporte' => $data['modalidad_transporte'],
                'vehiculo_placa' => $data['vehiculo_placa'] ?? null,
                'chofer_id' => $data['chofer_id'] ?? null,
                'punto_partida' => $data['punto_partida'],
                'punto_llegada' => $data['punto_llegada'],
                'almacen_origen_id' => $data['almacen_origen_id'],
                'almacen_destino_id' => $data['almacen_destino_id'] ?? null,
                'referencia' => $data['referencia'] ?? null,
                'observaciones' => $data['observaciones'] ?? null,
                'estado' => 'BORRADOR',
                'user_id' => $data['user_id'],
            ]);

            // Crear detalles de la guía
            $this->crearDetalles($guiaId, $data['detalles']);

            // Si afecta stock, descontar del almacén de origen
            if ($data['afecta_stock']) {
                $this->afectarStock($data['detalles'], 'descontar');
            }

            return $guia->load([
                'venta',
                'cliente',
                'motivoTraslado',
                'chofer',
                'almacenOrigen',
                'almacenDestino',
                'user',
                'detalles.producto.marca',
                'detalles.unidadDerivadaInmutable',
            ]);
        });
    }

    /**
     * Actualizar una guía de remisión (solo si está en BORRADOR)
     */
    public function actualizar(GuiaRemision $guia, array $data): GuiaRemision
    {
        if (!$guia->puedeEditarse()) {
            throw new \Exception('Solo se pueden editar guías en estado BORRADOR');
        }

        $guia->update($data);

        return $guia->fresh([
            'venta',
            'cliente',
            'motivoTraslado',
            'chofer',
            'almacenOrigen',
            'almacenDestino',
            'user',
            'detalles.producto.marca',
            'detalles.unidadDerivadaInmutable',
        ]);
    }

    /**
     * Emitir una guía (cambiar estado de BORRADOR a EMITIDA)
     */
    public function emitir(GuiaRemision $guia): GuiaRemision
    {
        return DB::transaction(function () use ($guia) {
            if ($guia->estado !== 'BORRADOR') {
                throw new \Exception('Solo se pueden emitir guías en estado BORRADOR');
            }

            $guia->update(['estado' => 'EMITIDA']);

            return $guia->fresh([
                'venta',
                'cliente',
                'motivoTraslado',
                'chofer',
                'almacenOrigen',
                'almacenDestino',
                'user',
                'detalles.producto.marca',
                'detalles.unidadDerivadaInmutable',
            ]);
        });
    }

    /**
     * Anular una guía (cambiar estado de EMITIDA a ANULADA)
     */
    public function anular(GuiaRemision $guia, string $motivoAnulacion): GuiaRemision
    {
        return DB::transaction(function () use ($guia, $motivoAnulacion) {
            if (!$guia->puedeAnularse()) {
                throw new \Exception('Solo se pueden anular guías en estado EMITIDA');
            }

            // Si afectó stock, revertir el descuento
            if ($guia->afecta_stock) {
                $detalles = $guia->detalles->map(function ($detalle) {
                    return [
                        'producto_almacen_id' => $detalle->producto_almacen_id,
                        'cantidad' => $detalle->cantidad,
                        'factor' => $detalle->factor,
                    ];
                })->toArray();

                $this->afectarStock($detalles, 'incrementar');
            }

            $guia->update([
                'estado' => 'ANULADA',
                'fecha_anulacion' => now(),
                'motivo_anulacion' => $motivoAnulacion,
            ]);

            return $guia->fresh([
                'venta',
                'cliente',
                'motivoTraslado',
                'chofer',
                'almacenOrigen',
                'almacenDestino',
                'user',
                'detalles.producto.marca',
                'detalles.unidadDerivadaInmutable',
            ]);
        });
    }

    /**
     * Eliminar una guía (solo si está en BORRADOR)
     */
    public function eliminar(GuiaRemision $guia): void
    {
        DB::transaction(function () use ($guia) {
            if ($guia->estado !== 'BORRADOR') {
                throw new \Exception('Solo se pueden eliminar guías en estado BORRADOR');
            }

            // Eliminar detalles
            DetalleGuiaRemision::where('guia_remision_id', $guia->id)->delete();

            // Eliminar guía
            $guia->delete();
        });
    }

    /**
     * Crear detalles de la guía
     */
    private function crearDetalles(string $guiaId, array $detalles): void
    {
        foreach ($detalles as $detalle) {
            DetalleGuiaRemision::create([
                'guia_remision_id' => $guiaId,
                'producto_id' => $detalle['producto_id'],
                'producto_almacen_id' => $detalle['producto_almacen_id'],
                'unidad_derivada_inmutable_id' => $detalle['unidad_derivada_inmutable_id'],
                'unidad_derivada_inmutable_name' => $detalle['unidad_derivada_inmutable_name'],
                'factor' => $detalle['factor'],
                'cantidad' => $detalle['cantidad'],
                'peso_total' => $detalle['peso_total'] ?? null,
                'unidad_derivada_venta_id' => $detalle['unidad_derivada_venta_id'] ?? null,
            ]);
        }
    }

    /**
     * Afectar el stock del almacén (descontar o incrementar)
     */
    private function afectarStock(array $detalles, string $accion): void
    {
        foreach ($detalles as $detalle) {
            $productoAlmacen = ProductoAlmacen::findOrFail($detalle['producto_almacen_id']);
            $cantidadBase = (float) $detalle['cantidad'] * (float) $detalle['factor'];

            if ($accion === 'descontar') {
                $productoAlmacen->decrement('stock', $cantidadBase);
            } elseif ($accion === 'incrementar') {
                $productoAlmacen->increment('stock', $cantidadBase);
            }
        }
    }
}
