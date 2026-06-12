<?php

namespace App\Services;

use App\Models\SerieDocumento;
use Illuminate\Support\Facades\DB;

/**
 * Servicio centralizado para la generación atómica de correlativos.
 *
 * RESOLVE EL RACE CONDITION: El patrón read-then-write ($serie->correlativo + 1
 * seguido de $serie->update) causaba duplicados cuando múltiples usuarios
 * creaban ventas simultáneamente. Esta clase usa LAST_INSERT_ID() para un
 * incremento atómico que MySQL garantiza ser secuencial sin importar la
 * concurrencia.
 *
 * Single Source of Truth: TODO correlativo de venta (boleta, factura, NV)
 * pasa por aquí.
 */
class SerieDocumentoService
{
    /**
     * Obtener la serie activa para un tipo de documento y almacén.
     *
     * @throws \Exception si no existe serie activa
     */
    public function obtenerSerieActiva(string $tipoDocumento, int $almacenId): SerieDocumento
    {
        $serieDoc = SerieDocumento::where('tipo_documento', $tipoDocumento)
            ->where('almacen_id', $almacenId)
            ->where('activo', true)
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $serieDoc) {
            throw new \Exception(
                "No se encontró una serie activa para el tipo de documento {$tipoDocumento} en el almacén {$almacenId}"
            );
        }

        return $serieDoc;
    }

    /**
     * Reservar el siguiente correlativo de forma ATÓMICA.
     *
     * Usa MySQL's LAST_INSERT_ID(expr) para incrementar y obtener el nuevo
     * valor en una sola operación atómica. Esto previene race conditions
     * porque:
     *
     *   1. LAST_LOCK_ID() es serial por conexión — dos conexiones concurrentes
     *      jamás obtienen el mismo resultado.
     *   2. La operación UPDATE + lectura ocurre en un solo paso dentro de la DB,
     *      no hay ventana entre read y write.
     *
     * REQUIERE:Debe llamarse DENTRO de un DB::transaction() ya existente.
     *
     * @param  string      $tipoDocumento  Código del tipo ('01', '03', 'nv')
     * @param  int         $almacenId      ID del almacén
     * @return array{serie: string, numero: int, serie_documento_id: int}
     *
     * @throws \Exception si no existe serie activa
     */
    public function reservarCorrelativo(string $tipoDocumento, int $almacenId): array
    {
        $serieDoc = $this->obtenerSerieActiva($tipoDocumento, $almacenId);

        // ── Incremento atómico ─────────────────────────────────────────────
        //
        // LAST_INSERT_ID(expr) en MySQL es la clave:
        //
        //   1. SET correlativo = LAST_INSERT_ID(correlativo + 1)
        //      → Incrementa el correlativo Y lo registra como "last insert id"
        //        de ESTA conexión. MySQL lo serializa internamente: si dos
        //        conexiones llegan al mismo tiempo, una recibe 151 y la otra 152.
        //
        //   2. SELECT LAST_INSERT_ID()
        //      → Devuelve el valor que acabamos de registrar. Es POR CONEXIÓN,
        //        no hay forma de que otra concurrente lo pise.
        //
        // Resultado: CADA venta recibe un número ÚNICO sin locks manuales,
        // sin SELECT FOR UPDATE, sin ventanas de lectura-escritura.
        //
        DB::statement(
            'UPDATE seriedocumento SET correlativo = LAST_INSERT_ID(correlativo + 1) WHERE id = ?',
            [$serieDoc->id]
        );

        $nuevoCorrelativo = (int) DB::selectOne('SELECT LAST_INSERT_ID() as id')->id;

        return [
            'serie'              => $serieDoc->serie,
            'numero'             => $nuevoCorrelativo,
            'serie_documento_id' => $serieDoc->id,
        ];
    }

    /**
     * Versión simplificada: solo devuelve serie + numero como los espera
     * VentaController (para no cambiar la forma en que se asignan al validated).
     *
     * @return array{serie: string, numero: int}
     */
    public function reservarCorrelativoSimple(string $tipoDocumento, int $almacenId): array
    {
        $resultado = $this->reservarCorrelativo($tipoDocumento, $almacenId);

        return [
            'serie'  => $resultado['serie'],
            'numero' => $resultado['numero'],
        ];
    }

    /**
     * Preview del siguiente número SIN incrementar.
     * Útil para el frontend que muestra "Siguiente: B001-486".
     */
    public function previewSiguienteNumero(string $tipoDocumento, int $almacenId): array
    {
        $serieDoc = $this->obtenerSerieActiva($tipoDocumento, $almacenId);

        return [
            'serie'  => $serieDoc->serie,
            'numero' => $serieDoc->correlativo + 1,
        ];
    }
}
