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
        private RequerimientoInternoRepositoryInterface $repository
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
                'area' => $data['area'],
                'fecha_requerida' => $data['fecha_requerida'],
                'prioridad' => $data['prioridad'],
                'tipo_solicitud' => $data['tipo_solicitud'],
                'observaciones' => $data['observaciones'] ?? null,
                'estado' => 'pendiente',
                'proveedor_sugerido_id' => $data['proveedor_sugerido_id'] ?? null,
                'user_id' => $data['user_id'],
            ]);

            // Crear productos (OC)
            if ($data['tipo_solicitud'] === 'OC') {
                if (empty($data['productos'])) {
                    throw RequerimientoInternoException::sinProductos();
                }

                foreach ($data['productos'] as $prod) {
                    RequerimientoInternoProducto::create([
                        'requerimiento_id' => $requerimiento->id,
                        'producto_id' => $prod['producto_id'],
                        'cantidad' => $prod['cantidad'],
                        'unidad' => $prod['unidad'] ?? null,
                    ]);
                }
            }

            // Crear servicio (OS)
            if ($data['tipo_solicitud'] === 'OS') {
                if (empty($data['servicio'])) {
                    throw RequerimientoInternoException::sinServicio();
                }

                $srv = $data['servicio'];
                RequerimientoInternoServicio::create([
                    'requerimiento_id' => $requerimiento->id,
                    'tipo_servicio' => $srv['tipo_servicio'] ?? null,
                    'descripcion_servicio' => $srv['descripcion_servicio'],
                    'lugar_ejecucion' => $srv['lugar_ejecucion'] ?? null,
                    'fecha_inicio_estimada' => $srv['fecha_inicio_estimada'] ?? null,
                    'presupuesto_referencial' => $srv['presupuesto_referencial'] ?? null,
                    'duracion_cantidad' => $srv['duracion_cantidad'] ?? null,
                    'duracion_unidad' => $srv['duracion_unidad'] ?? null,
                ]);
            }

            DB::commit();

            Log::info('Requerimiento interno creado', [
                'requerimiento_id' => $requerimiento->id,
                'codigo' => $codigo,
            ]);

            return $this->repository->findById($requerimiento->id);

        } catch (RequerimientoInternoException $e) {
            DB::rollBack();
            throw $e;
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

            Log::info('Estado de requerimiento cambiado', [
                'requerimiento_id' => $id,
                'estado_anterior' => $requerimiento->estado,
                'estado_nuevo' => $nuevoEstado,
            ]);

            return $this->repository->findById($id);

        } catch (RequerimientoInternoException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Error al cambiar estado de requerimiento', [
                'requerimiento_id' => $id,
                'error' => $e->getMessage(),
            ]);
            throw RequerimientoInternoException::errorAlActualizar($e->getMessage());
        }
    }
}
