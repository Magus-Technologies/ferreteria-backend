<?php

namespace App\Services\Implementations;

use App\Exceptions\CajaNoEncontradaException;
use App\Exceptions\SubCajaDuplicadaException;
use App\Models\AperturaCierreCaja;
use App\Models\CajaPrincipal;
use App\Models\SubCaja;
use App\Models\DespliegueDePago;
use App\Repositories\Interfaces\CajaPrincipalRepositoryInterface;
use App\Repositories\Interfaces\SubCajaRepositoryInterface;
use App\Services\Interfaces\CajaServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CajaService implements CajaServiceInterface
{
    public function __construct(
        private CajaPrincipalRepositoryInterface $cajaPrincipalRepository,
        private SubCajaRepositoryInterface $subCajaRepository
    ) {}

    public function crearCajaPrincipal(string $userId, string $nombre): CajaPrincipal
    {
        return DB::transaction(function () use ($userId, $nombre) {
            // Verificar si el usuario ya tiene una caja
            if ($this->cajaPrincipalRepository->existeCodigoParaUsuario($userId)) {
                throw new \Exception('El usuario ya tiene una caja principal asignada');
            }

            // Generar código
            $codigo = $this->cajaPrincipalRepository->generarSiguienteCodigo();

            // Crear caja principal
            $cajaPrincipal = $this->cajaPrincipalRepository->create([
                'codigo' => $codigo,
                'nombre' => $nombre,
                'user_id' => $userId,
                'estado' => 1,
            ]);

            // Crear automáticamente la Caja Chica
            $cajaChica = $this->crearCajaChicaAutomatica($cajaPrincipal->id, $codigo);
            
            // ✅ NUEVO: Crear apertura automática con monto 0
            AperturaCierreCaja::create([
                'id' => (string) Str::ulid(),
                'caja_principal_id' => $cajaPrincipal->id,
                'sub_caja_id' => $cajaChica->id,
                'user_id' => $userId,
                'monto_apertura' => 0.00,
                'fecha_apertura' => now(),
                'estado' => 'abierta',
            ]);

            return $cajaPrincipal->fresh(['user', 'subCajas']);
        });
    }

    private function crearCajaChicaAutomatica(int $cajaPrincipalId, string $codigoCajaPrincipal): SubCaja
    {
        // Buscar el despliegue de pago "Efectivo"
        $desplieguePagoEfectivo = DespliegueDePago::where('name', 'Efectivo')
            ->where('activo', true)
            ->where('mostrar', true)
            ->first();

        if (!$desplieguePagoEfectivo) {
            // Si no existe, intentar crear el método de pago y despliegue automáticamente
            \Log::warning('No se encontró despliegue de pago Efectivo, creando automáticamente...');
            
            // Crear método de pago Efectivo
            $metodoPagoEfectivo = \App\Models\MetodoDePago::firstOrCreate(
                ['name' => 'Efectivo'],
                [
                    'id' => (string) \Illuminate\Support\Str::ulid(),
                    'cuenta_bancaria' => null,
                    'monto' => 0.00,
                    'activo' => true,
                ]
            );

            // Crear despliegue de pago Efectivo
            $desplieguePagoEfectivo = DespliegueDePago::create([
                'id' => (string) \Illuminate\Support\Str::ulid(),
                'name' => 'Efectivo',
                'metodo_de_pago_id' => $metodoPagoEfectivo->id,
                'adicional' => 0.00,
                'activo' => true,
                'mostrar' => true,
            ]);
        }

        $codigo = $codigoCajaPrincipal . '-001';

        return $this->subCajaRepository->create([
            'codigo' => $codigo,
            'nombre' => 'Caja Chica',
            'caja_principal_id' => $cajaPrincipalId,
            'tipo_caja' => 'CC',
            'despliegues_pago_ids' => [$desplieguePagoEfectivo->id],
            'tipos_comprobante' => ['01', '03'], // Facturas (01) y Boletas (03)
            'saldo_actual' => 0.00,
            'proposito' => 'Efectivo de ventas con comprobantes oficiales (Facturas y Boletas)',
            'estado' => 1,
        ]);
    }

    public function crearSubCaja(int $cajaPrincipalId, array $data): SubCaja
    {
        return DB::transaction(function () use ($cajaPrincipalId, $data) {
            $cajaPrincipal = $this->cajaPrincipalRepository->findById($cajaPrincipalId);

            if (!$cajaPrincipal) {
                throw new CajaNoEncontradaException('Caja principal no encontrada');
            }

            // Validar configuración duplicada
            if ($this->subCajaRepository->existeConfiguracionDuplicada(
                $cajaPrincipalId,
                $data['despliegues_pago_ids'],
                $data['tipos_comprobante']
            )) {
                throw new SubCajaDuplicadaException();
            }

            // Generar código
            $codigo = $this->subCajaRepository->generarSiguienteCodigo($cajaPrincipal->codigo);

            return $this->subCajaRepository->create([
                'codigo' => $codigo,
                'nombre' => $data['nombre'],
                'caja_principal_id' => $cajaPrincipalId,
                'tipo_caja' => 'SC',
                'despliegues_pago_ids' => $data['despliegues_pago_ids'],
                'tipos_comprobante' => $data['tipos_comprobante'],
                'saldo_actual' => 0.00,
                'proposito' => $data['proposito'] ?? null,
                'estado' => 1,
            ]);
        });
    }

    public function actualizarSubCaja(int $subCajaId, array $data): SubCaja
    {
        return DB::transaction(function () use ($subCajaId, $data) {
            $subCaja = $this->subCajaRepository->findById($subCajaId);

            if (!$subCaja) {
                throw new CajaNoEncontradaException('Sub-caja no encontrada');
            }

            // No permitir modificar Caja Chica
            if ($subCaja->tipo_caja === 'CC') {
                throw new \Exception('No se puede modificar la Caja Chica');
            }

            // Validar configuración duplicada (excluyendo la actual)
            if (isset($data['despliegues_pago_ids']) && isset($data['tipos_comprobante'])) {
                if ($this->subCajaRepository->existeConfiguracionDuplicada(
                    $subCaja->caja_principal_id,
                    $data['despliegues_pago_ids'],
                    $data['tipos_comprobante'],
                    $subCajaId
                )) {
                    throw new SubCajaDuplicadaException();
                }
            }

            return $this->subCajaRepository->update($subCajaId, $data);
        });
    }

    public function eliminarSubCaja(int $subCajaId): bool
    {
        return DB::transaction(function () use ($subCajaId) {
            $subCaja = $this->subCajaRepository->findById($subCajaId);

            if (!$subCaja) {
                throw new CajaNoEncontradaException('Sub-caja no encontrada');
            }

            // No permitir eliminar Caja Chica
            if ($subCaja->tipo_caja === 'CC') {
                throw new \Exception('No se puede eliminar la Caja Chica');
            }

            // Validar que no tenga saldo
            if ($subCaja->saldo_actual > 0) {
                throw new \Exception('No se puede eliminar una sub-caja con saldo');
            }

            return $this->subCajaRepository->delete($subCajaId);
        });
    }

    public function obtenerCajaPorUsuario(string $userId): ?CajaPrincipal
    {
        return $this->cajaPrincipalRepository->findByUserId($userId);
    }

    public function obtenerSubCajas(int $cajaPrincipalId): array
    {
        return $this->subCajaRepository->findByCajaPrincipalId($cajaPrincipalId)->toArray();
    }
}
