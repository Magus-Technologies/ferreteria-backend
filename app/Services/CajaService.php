<?php

namespace App\Services;

use App\Models\CajaPrincipal;
use App\Models\SubCaja;
use App\Models\DespliegueDePago;
use App\Repositories\Interfaces\SubCajaRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Exception;

class CajaService
{
    protected $subCajaRepository;

    public function __construct(SubCajaRepositoryInterface $subCajaRepository)
    {
        $this->subCajaRepository = $subCajaRepository;
    }

    /**
     * Crear Caja Principal con su Caja Chica automática
     */
    public function crearCajaPrincipal(array $data): CajaPrincipal
    {
        return DB::transaction(function () use ($data) {
            // 1. Crear la Caja Principal
            $cajaPrincipal = CajaPrincipal::create([
                'codigo' => $data['codigo'],
                'nombre' => $data['nombre'],
                'user_id' => $data['user_id'],
                'estado' => true,
            ]);

            // 2. Obtener todos los métodos de efectivo desde desplieguedepago
            $metodosEfectivo = DespliegueDePago::whereHas('metodoDePago', function ($query) {
                $query->where('tipo', 'efectivo');
            })
            ->where('mostrar', 1)
            ->pluck('id')
            ->toArray();

            if (empty($metodosEfectivo)) {
                throw new Exception('No se encontraron métodos de pago en efectivo activos');
            }

            // 3. Crear la Caja Chica automáticamente
            $codigoCajaChica = $cajaPrincipal->codigo . '-001';
            
            SubCaja::create([
                'codigo' => $codigoCajaChica,
                'nombre' => 'sub_caja_chica_1',
                'caja_principal_id' => $cajaPrincipal->id,
                'tipo_caja' => 'CC',
                'despliegues_pago_ids' => $metodosEfectivo,
                'tipos_comprobante' => ['01', '03'], // Solo Facturas y Boletas
                'saldo_actual' => 0.00,
                'proposito' => 'Caja Chica - Efectivo para Facturas y Boletas',
                'estado' => true,
            ]);

            return $cajaPrincipal->fresh(['subCajas', 'user']);
        });
    }

    /**
     * Crear Sub-Caja
     */
    public function crearSubCaja(array $data): SubCaja
    {
        return DB::transaction(function () use ($data) {
            $cajaPrincipal = CajaPrincipal::findOrFail($data['caja_principal_id']);

            // Validar que no exista configuración duplicada
            if ($this->subCajaRepository->existeConfiguracionDuplicada(
                $cajaPrincipal->id,
                $data['despliegues_pago_ids'],
                $data['tipos_comprobante']
            )) {
                throw new Exception('Ya existe una sub-caja con esta configuración de métodos de pago y comprobantes');
            }

            // Generar código automático
            $codigo = $this->subCajaRepository->generarSiguienteCodigo($cajaPrincipal->codigo);

            return $this->subCajaRepository->create([
                'codigo' => $codigo,
                'nombre' => $data['nombre'],
                'caja_principal_id' => $cajaPrincipal->id,
                'tipo_caja' => 'SC',
                'despliegues_pago_ids' => $data['despliegues_pago_ids'],
                'tipos_comprobante' => $data['tipos_comprobante'],
                'saldo_actual' => 0.00,
                'proposito' => $data['proposito'] ?? null,
                'estado' => true,
            ]);
        });
    }

    /**
     * Actualizar Sub-Caja
     */
    public function actualizarSubCaja(int $id, array $data): SubCaja
    {
        return DB::transaction(function () use ($id, $data) {
            $subCaja = $this->subCajaRepository->findById($id);

            if (!$subCaja) {
                throw new Exception('Sub-caja no encontrada');
            }

            // No permitir modificar Caja Chica
            if ($subCaja->esCajaChica()) {
                throw new Exception('No se puede modificar la Caja Chica');
            }

            // Validar configuración duplicada (excluyendo la actual)
            if (isset($data['despliegues_pago_ids']) && isset($data['tipos_comprobante'])) {
                if ($this->subCajaRepository->existeConfiguracionDuplicada(
                    $subCaja->caja_principal_id,
                    $data['despliegues_pago_ids'],
                    $data['tipos_comprobante'],
                    $id
                )) {
                    throw new Exception('Ya existe otra sub-caja con esta configuración');
                }
            }

            return $this->subCajaRepository->update($id, $data);
        });
    }

    /**
     * Eliminar Sub-Caja
     */
    public function eliminarSubCaja(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $subCaja = $this->subCajaRepository->findById($id);

            if (!$subCaja) {
                throw new Exception('Sub-caja no encontrada');
            }

            if (!$subCaja->puedeEliminar()) {
                throw new Exception('No se puede eliminar: es Caja Chica o tiene saldo');
            }

            return $this->subCajaRepository->delete($id);
        });
    }

    /**
     * Asignar venta a sub-caja apropiada
     */
    public function asignarVentaASubCaja(
        int $cajaPrincipalId,
        string $tipoComprobante,
        string $desplieguePagoId
    ): ?SubCaja {
        return $this->subCajaRepository->buscarSubCajaParaVenta(
            $cajaPrincipalId,
            $tipoComprobante,
            $desplieguePagoId
        );
    }

    /**
     * Registrar transacción en sub-caja
     */
    public function registrarTransaccion(
        int $subCajaId,
        string $tipo,
        float $monto,
        string $descripcion,
        ?array $metadata = null
    ): void {
        DB::transaction(function () use ($subCajaId, $tipo, $monto, $descripcion, $metadata) {
            $subCaja = $this->subCajaRepository->findById($subCajaId);

            if (!$subCaja) {
                throw new Exception('Sub-caja no encontrada');
            }

            // Calcular nuevo saldo
            $nuevoSaldo = $tipo === 'ingreso' 
                ? $subCaja->saldo_actual + $monto 
                : $subCaja->saldo_actual - $monto;

            if ($nuevoSaldo < 0) {
                throw new Exception('Saldo insuficiente en la sub-caja');
            }

            // Actualizar saldo
            $this->subCajaRepository->actualizarSaldo($subCajaId, $nuevoSaldo);

            // Aquí se registraría en la tabla transacciones_caja
            // (implementar según tu modelo TransaccionCaja)
        });
    }
}
