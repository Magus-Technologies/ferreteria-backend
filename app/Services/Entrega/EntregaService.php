<?php

namespace App\Services\Entrega;

use App\Enums\CodigoEstadoEntrega;
use App\Enums\CodigoQuienEntrega;
use App\Enums\CodigoTipoDespacho;
use App\Enums\CodigoTipoEntrega;
use App\Exceptions\Entrega\TransicionInvalidaException;
use App\Models\Entrega;
use App\Models\EntregaDetalle;
use App\Models\EstadoEntrega;
use App\Models\QuienEntrega;
use App\Models\TipoDespacho;
use App\Models\TipoEntrega;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EntregaService
{
    public function __construct(
        private EntregaValidadorService         $validador,
        private EntregaStockService             $stock,
        private EntregaNotificacionService      $notificacion,
        private EntregaTransicionEstadoService  $transicion,
        private EntregaResumenService           $resumen,
        private EntregaSyncLegacyService        $syncLegacy,
    ) {}

    // ─────────────────────────────────────────────────────────────
    // CREAR
    // ─────────────────────────────────────────────────────────────

    /**
     * Crea una nueva entrega con sus detalles, aplica stock si corresponde
     * y envía notificaciones FCM.
     *
     * @param  array{
     *   venta_id: string,
     *   tipo_entrega: string,
     *   tipo_despacho: string|null,
     *   quien_entrega: string,
     *   almacen_salida_id: int,
     *   chofer_id: string|null,
     *   vehiculo_id: int|null,
     *   tipo_pedido: string,
     *   cargo_destino: string|null,
     *   fecha_programada: string|null,
     *   hora_inicio: string|null,
     *   hora_fin: string|null,
     *   direccion_entrega: string|null,
     *   referencia_entrega: string|null,
     *   latitud: float|null,
     *   longitud: float|null,
     *   observaciones: string|null,
     *   productos: array<int, array{unidad_derivada_venta_id: int, cantidad: float, ubicacion: string|null}>,
     *   user_creador_id: string,
     * }  $data
     */
    public function crear(array $data): Entrega
    {
        $quienEntrega = CodigoQuienEntrega::from($data['quien_entrega']);
        $tipoDespacho = isset($data['tipo_despacho'])
            ? CodigoTipoDespacho::from($data['tipo_despacho'])
            : null;

        // Validar cantidades pendientes
        $this->validador->validarCantidadesPendientes($data['venta_id'], $data['productos']);

        // Validar chofer disponible
        if ($data['chofer_id'] ?? null) {
            $this->validador->validarChoferDisponible($data['chofer_id']);
        }

        return DB::transaction(function () use ($data, $quienEntrega, $tipoDespacho) {
            $estadoInicial = $this->transicion->estadoInicial($quienEntrega, $tipoDespacho);

            $tipoEntregaId = TipoEntrega::where('codigo', $data['tipo_entrega'])->value('id');
            $tipoDespachoId = $tipoDespacho
                ? TipoDespacho::where('codigo', $tipoDespacho->value)->value('id')
                : null;
            $estadoEntregaId = EstadoEntrega::where('codigo', $estadoInicial->value)->value('id');
            $quienEntregaId  = QuienEntrega::where('codigo', $quienEntrega->value)->value('id');

            $userEntregadoId = $estadoInicial === CodigoEstadoEntrega::Entregado
                ? $data['user_creador_id']
                : null;

            $entrega = Entrega::create([
                'venta_id'                => $data['venta_id'],
                'venta_entrega_secuencia' => $this->resumen->siguienteSecuencia($data['venta_id']),
                'tipo_entrega_id'         => $tipoEntregaId,
                'tipo_despacho_id'        => $tipoDespachoId,
                'estado_entrega_id'       => $estadoEntregaId,
                'quien_entrega_id'        => $quienEntregaId,
                'almacen_salida_id'       => $data['almacen_salida_id'],
                'chofer_id'               => $data['chofer_id'] ?? null,
                'vehiculo_id'             => $data['vehiculo_id'] ?? null,
                'tipo_pedido'             => $data['tipo_pedido'] ?? 'interno',
                'cargo_destino'           => $data['cargo_destino'] ?? null,
                'user_creador_id'         => $data['user_creador_id'],
                'user_entregado_id'       => $userEntregadoId,
                'fecha_creacion'          => now()->toDateString(),
                'fecha_ejecutada'         => $estadoInicial === CodigoEstadoEntrega::Entregado ? now() : null,
                'fecha_programada'        => $data['fecha_programada'] ?? null,
                'hora_inicio'             => $data['hora_inicio'] ?? null,
                'hora_fin'                => $data['hora_fin'] ?? null,
                'direccion_entrega'       => $data['direccion_entrega'] ?? null,
                'referencia_entrega'      => $data['referencia_entrega'] ?? null,
                'latitud'                 => $data['latitud'] ?? null,
                'longitud'                => $data['longitud'] ?? null,
                'observaciones'           => $data['observaciones'] ?? null,
            ]);

            foreach ($data['productos'] as $item) {
                EntregaDetalle::create([
                    'entrega_id'               => $entrega->id,
                    'unidad_derivada_venta_id' => $item['unidad_derivada_venta_id'],
                    'cantidad'                 => $item['cantidad'],
                    'ubicacion'                => $item['ubicacion'] ?? null,
                ]);
            }

            // Aplicar stock AL PROGRAMAR/CREAR la entrega — no esperar a que se
            // confirme la entrega en mis-entregas. Regla de negocio: el descuento
            // ocurre al programar desde Configurar Entrega (mis-ventas).
            // Es idempotente vía `stock_aplicado`: confirmar / marcar en-camino
            // después NO vuelve a descontar, y anular lo revierte.
            $this->stock->aplicar($entrega->fresh());

            if ($entrega->chofer_id || $entrega->cargo_destino) {
                $this->notificacion->notificarAsignacion($entrega->load('venta'));
            }

            return $entrega->fresh($this->eagerLoadDefault());
        });
    }

    // ─────────────────────────────────────────────────────────────
    // CONFIRMAR (pe/ec → en)
    // ─────────────────────────────────────────────────────────────

    public function confirmar(Entrega $entrega, string $userId): Entrega
    {
        return DB::transaction(function () use ($entrega, $userId) {
            $this->transicion->transicionar($entrega, CodigoEstadoEntrega::Entregado, $userId);
            $this->stock->aplicar($entrega->fresh());
            // Espejar estado al legacy + recalcular cantidad_pendiente para que
            // la columna "Entrega" de mis-ventas refleje el cambio.
            $this->syncLegacy->sincronizar($entrega->fresh());
            return $entrega->fresh($this->eagerLoadDefault());
        });
    }

    // ─────────────────────────────────────────────────────────────
    // EN CAMINO (pe → ec)
    // ─────────────────────────────────────────────────────────────

    public function marcarEnCamino(Entrega $entrega, string $userId): Entrega
    {
        return DB::transaction(function () use ($entrega, $userId) {
            $this->transicion->transicionar($entrega, CodigoEstadoEntrega::EnCamino, $userId);
            $this->stock->aplicar($entrega->fresh());
            $this->syncLegacy->sincronizar($entrega->fresh());
            return $entrega->fresh($this->eagerLoadDefault());
        });
    }

    // ─────────────────────────────────────────────────────────────
    // ANULAR (cualquier estado → ca)
    // ─────────────────────────────────────────────────────────────

    public function anular(Entrega $entrega, string $userId, string $motivo): Entrega
    {
        return DB::transaction(function () use ($entrega, $userId, $motivo) {
            $this->transicion->transicionar($entrega, CodigoEstadoEntrega::Cancelado, $userId);

            $entrega->update(['motivo_anulacion' => $motivo]);

            $this->stock->revertir($entrega->fresh());

            // Anular saca esta entrega del cómputo (estado 'ca') → recalcular
            // cantidad_pendiente y espejar el estado al legacy.
            $this->syncLegacy->sincronizar($entrega->fresh());

            return $entrega->fresh($this->eagerLoadDefault());
        });
    }

    // ─────────────────────────────────────────────────────────────
    // REASIGNAR CHOFER
    // ─────────────────────────────────────────────────────────────

    public function reasignarChofer(Entrega $entrega, string $nuevoChoferId, ?int $nuevoVehiculoId): Entrega
    {
        $this->validador->validarNoEsFinal($entrega);
        $this->validador->validarChoferDisponible($nuevoChoferId);

        $anteriorChoferId = $entrega->chofer_id;

        $entrega->update([
            'chofer_id'   => $nuevoChoferId,
            'vehiculo_id' => $nuevoVehiculoId,
        ]);

        $this->notificacion->notificarReasignacion($entrega->fresh('venta'), $anteriorChoferId);

        return $entrega->fresh($this->eagerLoadDefault());
    }

    // ─────────────────────────────────────────────────────────────
    // ACTUALIZAR DATOS LOGÍSTICOS (sin cambio de estado)
    // ─────────────────────────────────────────────────────────────

    public function actualizar(Entrega $entrega, array $data): Entrega
    {
        $this->validador->validarNoEsFinal($entrega);

        $campos = array_filter([
            'chofer_id'          => $data['chofer_id'] ?? null,
            'vehiculo_id'        => $data['vehiculo_id'] ?? null,
            'fecha_programada'   => $data['fecha_programada'] ?? null,
            'hora_inicio'        => $data['hora_inicio'] ?? null,
            'hora_fin'           => $data['hora_fin'] ?? null,
            'direccion_entrega'  => $data['direccion_entrega'] ?? null,
            'referencia_entrega' => $data['referencia_entrega'] ?? null,
            'latitud'            => $data['latitud'] ?? null,
            'longitud'           => $data['longitud'] ?? null,
            'observaciones'      => $data['observaciones'] ?? null,
        ], fn ($v) => $v !== null);

        $entrega->update($campos);

        return $entrega->fresh($this->eagerLoadDefault());
    }

    // ─────────────────────────────────────────────────────────────
    // SYNC DESDE VENTA CONTROLLER
    // ─────────────────────────────────────────────────────────────

    /**
     * Crea entrega + detalles en las tablas nuevas mirroreando lo que
     * VentaController ya persistió en las tablas legacy.
     *
     * NO aplica stock (VentaController ya lo manejó),
     * NO valida cantidades pendientes,
     * NO envía notificaciones.
     *
     * @param array{
     *   venta_id: string,
     *   tipo_entrega: string,
     *   tipo_despacho: string,
     *   estado_entrega: string,
     *   quien_entrega: string,
     *   almacen_salida_id: int,
     *   user_creador_id: string,
     *   user_entregado_id: string|null,
     *   fecha_creacion: string,
     *   fecha_ejecutada: string|null,
     *   tipo_pedido: string,
     *   chofer_id?: string|null,
     *   vehiculo_id?: int|null,
     *   fecha_programada?: string|null,
     *   hora_inicio?: string|null,
     *   hora_fin?: string|null,
     *   direccion_entrega?: string|null,
     *   referencia_entrega?: string|null,
     *   latitud?: float|null,
     *   longitud?: float|null,
     *   observaciones?: string|null,
     *   entrega_legacy_id: int|null,
     *   productos: array<int, array{unidad_derivada_venta_id: int, cantidad: float}>,
     * } $data
     */
    public function crearSync(array $data): Entrega
    {
        $tipoEntregaId  = TipoEntrega::where('codigo', $data['tipo_entrega'])->value('id');
        $tipoDespachoId = TipoDespacho::where('codigo', $data['tipo_despacho'])->value('id');
        $estadoEntregaId = EstadoEntrega::where('codigo', $data['estado_entrega'])->value('id');
        $quienEntregaId  = QuienEntrega::where('codigo', $data['quien_entrega'])->value('id');

        $entrega = Entrega::create([
            'venta_id'                => $data['venta_id'],
            'venta_entrega_secuencia' => $this->resumen->siguienteSecuencia($data['venta_id']),
            'tipo_entrega_id'         => $tipoEntregaId,
            'tipo_despacho_id'        => $tipoDespachoId,
            'estado_entrega_id'       => $estadoEntregaId,
            'quien_entrega_id'        => $quienEntregaId,
            'almacen_salida_id'       => $data['almacen_salida_id'],
            'tipo_pedido'             => $data['tipo_pedido'] ?? 'interno',
            'chofer_id'               => $data['chofer_id'] ?? null,
            'vehiculo_id'             => $data['vehiculo_id'] ?? null,
            'user_creador_id'         => $data['user_creador_id'],
            'user_entregado_id'       => $data['user_entregado_id'] ?? null,
            'fecha_creacion'          => $data['fecha_creacion'],
            'fecha_programada'        => $data['fecha_programada'] ?? null,
            'fecha_ejecutada'         => $data['fecha_ejecutada'] ?? null,
            'hora_inicio'             => $data['hora_inicio'] ?? null,
            'hora_fin'                => $data['hora_fin'] ?? null,
            'direccion_entrega'       => $data['direccion_entrega'] ?? null,
            'referencia_entrega'      => $data['referencia_entrega'] ?? null,
            'latitud'                 => $data['latitud'] ?? null,
            'longitud'                => $data['longitud'] ?? null,
            'observaciones'           => $data['observaciones'] ?? null,
            // Stock ya aplicado por VentaController — evita doble decremento
            'stock_aplicado'          => true,
            'entrega_legacy_id'       => $data['entrega_legacy_id'] ?? null,
        ]);

        foreach ($data['productos'] as $item) {
            EntregaDetalle::create([
                'entrega_id'               => $entrega->id,
                'unidad_derivada_venta_id' => $item['unidad_derivada_venta_id'],
                'cantidad'                 => $item['cantidad'],
            ]);
        }

        return $entrega;
    }

    // ─────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────

    private function eagerLoadDefault(): array
    {
        return [
            'tipoEntrega',
            'tipoDespacho',
            'estadoEntrega',
            'quienEntrega',
            'venta:id,serie,numero,cliente_id',
            'venta.cliente:id,nombres,apellidos,razon_social,telefono',
            'almacenSalida:id,name',
            'chofer:id,name',
            'vehiculo:id,name,tipo,placa',
            'entregaLegacy:id,chofer_id,vehiculo_id,fecha_programada,hora_inicio,hora_fin,direccion_entrega,referencia_entrega,latitud,longitud,observaciones',
            'entregaLegacy.despachador:id,name',
            'entregaLegacy.vehiculo:id,name,tipo,placa',
            'detalles.unidadDerivadaVenta.productoAlmacenVenta.productoAlmacen.producto',
        ];
    }
}
