<?php

namespace App\Services\Implementations;

use App\Exceptions\OrdenCompraException;
use App\Models\HistorialUnidadDerivadaInmutableRecepcion;
use App\Models\OrdenCompra;
use App\Models\OrdenCompraProducto;
use App\Models\RecepcionAlmacen;
use App\Models\ProductoAlmacen;
use App\Models\ProductoAlmacenRecepcion;
use App\Models\ProductoAlmacenUnidadDerivada;
use App\Models\UnidadDerivadaInmutable;
use App\Models\UnidadDerivadaInmutableRecepcion;
use App\Repositories\Interfaces\OrdenCompraRepositoryInterface;
use App\Services\Cache\ProductoCacheService;
use App\Services\Interfaces\OrdenCompraServiceInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrdenCompraService implements OrdenCompraServiceInterface
{
    public function __construct(
        private OrdenCompraRepositoryInterface $repository
    ) {}

    public function listarPaginado(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $ordenes = $this->repository->getPaginated($filters, $perPage);

        // Agregar totales calculados a cada orden
        $ordenes->getCollection()->transform(function ($orden) {
            $orden->total = $orden->productos->sum('subtotal');
            $orden->total_flete = $orden->productos->sum('flete');
            return $orden;
        });

        return $ordenes;
    }

    public function obtenerPorId(int $id): OrdenCompra
    {
        $orden = $this->repository->findById($id);

        if (!$orden) {
            throw OrdenCompraException::noEncontrada($id);
        }

        $orden->total = $orden->productos->sum('subtotal');
        $orden->total_flete = $orden->productos->sum('flete');

        return $orden;
    }

    public function crear(array $data): OrdenCompra
    {
        try {
            DB::beginTransaction();

            if (empty($data['productos'])) {
                throw OrdenCompraException::sinProductos();
            }

            $codigo = OrdenCompra::generarCodigo();

            $orden = $this->repository->create([
                'codigo' => $codigo,
                'requerimiento_id' => $data['requerimiento_id'] ?? null,
                'proveedor_id' => $data['proveedor_id'] ?? null,
                'fecha' => $data['fecha'],
                'tipo_moneda' => $data['tipo_moneda'] ?? 's',
                'tipo_de_cambio' => $data['tipo_de_cambio'] ?? 1.0000,
                'ruc' => $data['ruc'] ?? null,
                'tipo_documento' => $data['tipo_documento'] ?? null,
                'serie' => $data['serie'] ?? null,
                'numero' => $data['numero'] ?? null,
                'guia' => $data['guia'] ?? null,
                'percepcion' => $data['percepcion'] ?? 0,
                'forma_de_pago' => $data['forma_de_pago'] ?? 'co',
                'numero_dias' => $data['numero_dias'] ?? null,
                'fecha_vencimiento' => $data['fecha_vencimiento'] ?? null,
                'egreso_dinero_id' => $data['egreso_dinero_id'] ?? null,
                'despliegue_de_pago_id' => $data['despliegue_de_pago_id'] ?? null,
                'estado' => 'pendiente',
                'user_id' => $data['user_id'],
                'almacen_id' => $data['almacen_id'],
            ]);

            // Crear productos
            foreach ($data['productos'] as $prod) {
                OrdenCompraProducto::create([
                    'orden_compra_id' => $orden->id,
                    'producto_id' => $prod['producto_id'],
                    'codigo' => $prod['codigo'] ?? null,
                    'nombre' => $prod['nombre'] ?? null,
                    'marca' => $prod['marca'] ?? null,
                    'unidad' => $prod['unidad'] ?? null,
                    'cantidad' => $prod['cantidad'],
                    'precio' => $prod['precio'],
                    'subtotal' => $prod['subtotal'],
                    'flete' => $prod['flete'] ?? 0,
                    'vencimiento' => $prod['vencimiento'] ?? null,
                    'lote' => $prod['lote'] ?? null,
                ]);
            }

            DB::commit();

            Log::info('Orden de compra creada', [
                'orden_id' => $orden->id,
                'codigo' => $codigo,
            ]);

            return $this->repository->findById($orden->id);

        } catch (OrdenCompraException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear orden de compra', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw OrdenCompraException::errorAlCrear($e->getMessage());
        }
    }

    public function anular(int $id): OrdenCompra
    {
        try {
            $orden = $this->repository->findById($id);

            if (!$orden) {
                throw OrdenCompraException::noEncontrada($id);
            }

            if ($orden->estado?->value === 'anulada') {
                throw OrdenCompraException::yaAnulada();
            }

            if (!in_array($orden->estado?->value, ['pendiente', 'en_proceso'])) {
                throw OrdenCompraException::noAnulable($orden->estado?->value);
            }

            $this->repository->cambiarEstado($id, 'anulada');

            Log::info('Orden de compra anulada', [
                'orden_id' => $id,
                'codigo' => $orden->codigo,
            ]);

            return $this->repository->findById($id);

        } catch (OrdenCompraException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Error al anular orden de compra', [
                'orden_id' => $id,
                'error' => $e->getMessage(),
            ]);
            throw OrdenCompraException::errorAlAnular($e->getMessage());
        }
    }

    public function aprobar(int $id): OrdenCompra
    {
        try {
            $orden = $this->repository->findById($id);

            if (!$orden) {
                throw OrdenCompraException::noEncontrada($id);
            }

            if ($orden->estado?->value !== 'pendiente') {
                throw new OrdenCompraException("La orden debe estar en estado pendiente para ser aprobada");
            }

            DB::beginTransaction();

            $this->repository->cambiarEstado($id, 'en_proceso');

            // Crear recepción automáticamente
            $this->crearRecepcionAutomatica($id, $orden);

            DB::commit();

            Log::info('Orden de compra aprobada', [
                'orden_id' => $id,
                'codigo' => $orden->codigo,
            ]);

            return $this->repository->findById($id);

        } catch (OrdenCompraException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al aprobar orden de compra', [
                'orden_id' => $id,
                'error' => $e->getMessage(),
            ]);
            throw new OrdenCompraException("Error al aprobar la orden: " . $e->getMessage());
        }
    }

    private function crearRecepcionAutomatica(int $ordenId, OrdenCompra $orden): void
    {
        // productos ya cargados por repository::findById
        $numero = (DB::table('recepcionalmacen')->max('numero') ?? 0) + 1;

        $recepcion = RecepcionAlmacen::create([
            'numero'          => $numero,
            'compra_id'       => null,
            'orden_compra_id' => $ordenId,
            'user_id'         => $orden->user_id,
            'fecha'           => now(),
            'observaciones'   => "Recepción automática de Orden de Compra {$orden->codigo}",
            'estado'          => true,
        ]);

        // Batch-load ProductoAlmacen para evitar N+1
        $productosAlmacen = ProductoAlmacen::whereIn('producto_id', $orden->productos->pluck('producto_id'))
            ->where('almacen_id', $orden->almacen_id)
            ->get()
            ->keyBy('producto_id');

        // Pre-cargar UnidadDerivadaInmutable únicos para evitar N+1
        $uniqueUnits  = $orden->productos->pluck('unidad')->filter()->unique();
        $udInmutableMap = UnidadDerivadaInmutable::whereIn('name', $uniqueUnits)->get()->keyBy('name');
        foreach ($uniqueUnits as $unitName) {
            if (!$udInmutableMap->has($unitName)) {
                $udInmutableMap->put($unitName, UnidadDerivadaInmutable::create(['name' => $unitName]));
            }
        }

        // Acumular incrementos por producto_almacen_id para evitar lecturas de stock desactualizadas
        $stockIncrementos = [];

        foreach ($orden->productos as $prod) {
            $productoAlmacen = $productosAlmacen->get($prod->producto_id);

            if (!$productoAlmacen) {
                Log::warning('ProductoAlmacen no encontrado al crear recepción automática', [
                    'producto_id' => $prod->producto_id,
                    'almacen_id'  => $orden->almacen_id,
                ]);
                continue;
            }

            $udConfig = ProductoAlmacenUnidadDerivada::where('producto_almacen_id', $productoAlmacen->id)
                ->whereHas('unidadDerivada', fn ($q) => $q->where('name', $prod->unidad))
                ->first();
            $factor = $udConfig ? (float) $udConfig->factor : 1.0;

            $par = ProductoAlmacenRecepcion::create([
                'recepcion_id'        => $recepcion->id,
                'costo'               => $prod->precio,
                'producto_almacen_id' => $productoAlmacen->id,
            ]);

            $cantidad   = (float) $prod->cantidad;
            $incremento = $cantidad * $factor;
            $paId       = $productoAlmacen->id;

            // Stock anterior real = stock original + incrementos ya aplicados en esta recepción
            $stockAnterior = (float) $productoAlmacen->stock_fraccion + ($stockIncrementos[$paId] ?? 0);
            $stockIncrementos[$paId] = ($stockIncrementos[$paId] ?? 0) + $incremento;

            $udRecepcion = UnidadDerivadaInmutableRecepcion::create([
                'unidad_derivada_inmutable_id'  => $udInmutableMap->get($prod->unidad)->id,
                'producto_almacen_recepcion_id' => $par->id,
                'factor'                        => $factor,
                'cantidad'                      => $cantidad,
                'cantidad_restante'             => $cantidad,
                'lote'                          => $prod->lote,
                'vencimiento'                   => $prod->vencimiento,
                'flete'                         => $prod->flete ?? 0,
                'bonificacion'                  => false,
            ]);

            HistorialUnidadDerivadaInmutableRecepcion::create([
                'unidad_derivada_inmutable_recepcion_id' => $udRecepcion->id,
                'stock_anterior' => $stockAnterior,
                'stock_nuevo'    => $stockAnterior + $incremento,
            ]);

            $update = ['stock_fraccion' => DB::raw("stock_fraccion + {$incremento}")];
            if ($stockAnterior <= 0) {
                $update['costo'] = $prod->precio;
            }
            ProductoAlmacen::where('id', $paId)->update($update);
        }

        // Invalidar caché una sola vez para todo el almacén
        app(ProductoCacheService::class)->invalidateProductosAlmacen($orden->almacen_id);

        Log::info('Recepción automática creada para OrdenCompra', [
            'orden_id'  => $ordenId,
            'codigo'    => $orden->codigo,
            'numero'    => $numero,
            'productos' => $orden->productos->count(),
        ]);
    }
}
