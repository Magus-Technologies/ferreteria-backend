<?php

namespace App\Services\Implementations;

use App\Exceptions\RequerimientoInternoException;
use App\Models\RequerimientoInterno;
use App\Models\RequerimientoInternoProducto;
use App\Models\RequerimientoInternoServicio;
use App\Repositories\Interfaces\RequerimientoInternoRepositoryInterface;
use App\Services\Interfaces\RequerimientoInternoServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RequerimientoInternoService implements RequerimientoInternoServiceInterface
{
    public function __construct(
        private RequerimientoInternoRepositoryInterface $repository,
        private \App\Services\FirebaseNotificationService $notificationService
    ) {}

    public function listarPaginado(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->repository->getPaginated($filters, $perPage);
    }

    public function getAprobadosOC(): Collection
    {
        return $this->repository->getAprobadosOC();
    }

    public function obtenerPorId(int $id): RequerimientoInterno
    {
        $requerimiento = $this->repository->findById($id);

        if (!$requerimiento) {
            throw RequerimientoInternoException::noEncontrado($id);
        }

        return $requerimiento;
    }

    public function crear(array $data): RequerimientoInterno
    {
        try {
            DB::beginTransaction();

            $codigo = RequerimientoInterno::generarCodigo();

            $requerimiento = $this->repository->create([
                'codigo' => $codigo,
                'titulo' => $data['titulo'],
                'cargo' => $data['cargo'],
                'assigned_cargo_id' => $data['assigned_cargo_id'] ?? null,
                'fecha_requerida' => $data['fecha_requerida'],
                'prioridad' => $data['prioridad'],
                'tipo_solicitud' => $data['tipo_solicitud'],
                'observaciones' => $data['observaciones'] ?? null,
                'afecta_calendario' => $data['afecta_calendario'] ?? false,
                'vehiculo_id' => $data['vehiculo_id'] ?? null,
                'duracion_cantidad' => $data['duracion_cantidad'] ?? null,
                'duracion_unidad' => $data['duracion_unidad'] ?? null,
                'estado' => 'pendiente',
                'approval_state' => 'pendiente',
                'proveedor_sugerido_id' => $data['proveedor_sugerido_id'] ?? null,
                'user_id' => $data['user_id'],
            ]);

            // Crear productos (OC y SOC)
            if (in_array($data['tipo_solicitud'], ['OC', 'SOC'])) {
                if (empty($data['productos'])) {
                    throw RequerimientoInternoException::sinProductos();
                }

                foreach ($data['productos'] as $prod) {
                    // Validar que al menos tenga producto_id o nombre_adicional
                    if (empty($prod['producto_id']) && empty($prod['nombre_adicional'])) {
                        throw new \InvalidArgumentException('Cada producto debe tener un ID o nombre adicional');
                    }

                    $cantidad = $prod['cantidad'];
                    RequerimientoInternoProducto::create([
                        'requerimiento_id' => $requerimiento->id,
                        'producto_id' => $prod['producto_id'] ?? null,
                        'nombre_adicional' => $prod['nombre_adicional'] ?? null,
                        'cantidad' => $cantidad,
                        'cantidad_pendiente' => $cantidad, // Inicializar con la cantidad total
                        'unidad' => $prod['unidad'] ?? null,
                    ]);
                }
            }

            // Crear servicios (OS)
            if ($data['tipo_solicitud'] === 'OS') {
                if (empty($data['servicios'])) {
                    throw RequerimientoInternoException::sinServicios();
                }

                foreach ($data['servicios'] as $srv) {
                    if (empty($srv['descripcion_servicio'])) {
                        throw new \InvalidArgumentException('La descripción del servicio es requerida');
                    }

                    RequerimientoInternoServicio::create([
                        'requerimiento_id' => $requerimiento->id,
                        'tipo_servicio' => $srv['tipo_servicio'] ?? null,
                        'descripcion_servicio' => $srv['descripcion_servicio'],
                        'lugar_ejecucion' => $srv['lugar_ejecucion'] ?? null,
                        'fecha_inicio_estimada' => $srv['fecha_inicio_estimada'] ?? null,
                        'presupuesto_referencial' => $srv['presupuesto_referencial'] ?? null,
                        'detalles' => $srv['detalles'] ?? null,
                    ]);
                }
            }

            // Notificar a administradores
            try {
                $this->notificationService->sendToRole(
                    'ADMINISTRADOR',
                    '📋 Nuevo Requerimiento Interno',
                    "Se ha creado el requerimiento {$requerimiento->codigo}: {$requerimiento->titulo}",
                    [
                        'id' => (string) $requerimiento->id,
                        'type' => 'requerimiento_interno',
                        'codigo' => $requerimiento->codigo
                    ]
                );
            } catch (\Exception $e) {
            }

            DB::commit();


            return $this->repository->findById($requerimiento->id);

        } catch (RequerimientoInternoException $e) {
            DB::rollBack();
            throw $e;
        } catch (\InvalidArgumentException $e) {
            DB::rollBack();
            throw RequerimientoInternoException::errorAlCrear($e->getMessage());
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear requerimiento interno', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw RequerimientoInternoException::errorAlCrear($e->getMessage());
        }
    }

    public function cambiarEstado(int $id, string $nuevoEstado): RequerimientoInterno
    {
        try {
            DB::beginTransaction();

            $requerimiento = $this->repository->findById($id);

            if (!$requerimiento) {
                throw RequerimientoInternoException::noEncontrado($id);
            }

            // Validar transiciones de estado
            $transicionesValidas = [
                'pendiente' => ['aprobado', 'rechazado', 'anulado'],
                'aprobado' => ['anulado'],
                'rechazado' => ['pendiente'],
                'anulado' => [],
            ];

            $permitidos = $transicionesValidas[$requerimiento->estado] ?? [];

            if (!in_array($nuevoEstado, $permitidos)) {
                throw RequerimientoInternoException::estadoInvalido(
                    $requerimiento->estado,
                    $nuevoEstado
                );
            }

            $this->repository->cambiarEstado($id, $nuevoEstado);
            $requerimiento = $this->repository->findById($id);

            // Si se aprueba y afecta calendario con vehiculo_id -> crear bloqueo
            if ($nuevoEstado === 'aprobado' && $requerimiento->afecta_calendario && $requerimiento->vehiculo_id) {
                \App\Models\VehiculoMantenimiento::create([
                    'vehiculo_id' => $requerimiento->vehiculo_id,
                    'requerimiento_id' => $requerimiento->id,
                    'tipo' => 'mantenimiento',
                    'descripcion' => 'Bloque creado por aprobación de requerimiento ' . $requerimiento->codigo,
                    'fecha_inicio' => $requerimiento->fecha_requerida ?? now(),
                    'fecha_fin' => $requerimiento->fecha_requerida ? \Carbon\Carbon::parse($requerimiento->fecha_requerida)->addHours(2) : now()->addHours(2),
                    'estado' => 'aprobado',
                ]);
            }

            DB::commit();

            return $this->repository->findById($id);

        } catch (RequerimientoInternoException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al cambiar estado de requerimiento', [
                'requerimiento_id' => $id,
                'error' => $e->getMessage(),
            ]);
            throw RequerimientoInternoException::errorAlActualizar($e->getMessage());
        }
    }

    public function actualizarCantidadOrdenada(
        int $productoId,
        float $cantidadOrdenada,
        ?int $ordenCompraId = null,
        ?string $ordenCompraCodigo = null
    ): void {
        try {
            RequerimientoInternoProducto::where('id', $productoId)->update([
                'cantidad_ordenada' => $cantidadOrdenada,
                'orden_compra_id' => $ordenCompraId,
                'orden_compra_codigo' => $ordenCompraCodigo,
            ]);


        } catch (\Exception $e) {
            Log::error('Error al actualizar cantidad ordenada', [
                'producto_id' => $productoId,
                'error' => $e->getMessage(),
            ]);
            throw RequerimientoInternoException::errorAlActualizar($e->getMessage());
        }
    }
}
