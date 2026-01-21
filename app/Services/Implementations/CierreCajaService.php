<?php

namespace App\Services\Implementations;

use App\DTOs\CierreCaja\CajaActivaDTO;
use App\DTOs\CierreCaja\CierreCajaDTO;
use App\DTOs\CierreCaja\CierreCajaResultadoDTO;
use App\Exceptions\AperturaNoEncontradaException;
use App\Queries\CierreCaja\MovimientosCajaQuery;
use App\Repositories\Interfaces\AperturaCierreCajaRepositoryInterface;
use App\Services\CierreCaja\CalculadorResumenCaja;
use App\Services\Interfaces\CierreCajaServiceInterface;
use App\UseCases\CierreCaja\CerrarCajaUseCase;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

/**
 * Servicio refactorizado siguiendo principios SOLID
 * 
 * Este servicio actúa como facade/orquestador ligero
 * Delega responsabilidades específicas a:
 * - UseCases: Lógica de negocio
 * - Queries: Consultas complejas
 * - Services: Cálculos y validaciones
 * - Repositories: Acceso a datos
 */
class CierreCajaService implements CierreCajaServiceInterface
{
    public function __construct(
        private AperturaCierreCajaRepositoryInterface $aperturaRepository,
        private CerrarCajaUseCase $cerrarCajaUseCase,
        private CalculadorResumenCaja $calculadorResumen,
        private MovimientosCajaQuery $movimientosQuery
    ) {}

    public function obtenerCajaActivaConResumen(string $userId): CajaActivaDTO
    {
        \Log::info('=== CierreCajaService::obtenerCajaActivaConResumen ===', [
            'userId' => $userId,
            'type' => gettype($userId)
        ]);
        
        $apertura = $this->aperturaRepository->findCajaActiva($userId);
        \Log::info('Apertura obtenida del repositorio', ['apertura' => $apertura ? 'encontrada' : 'null']);

        if (!$apertura) {
            \Log::warning('No se encontró apertura activa');
            throw new AperturaNoEncontradaException();
        }

        \Log::info('Calculando resumen');
        $resumen = $this->calculadorResumen->calcular($apertura);
        \Log::info('Resumen calculado');

        return new CajaActivaDTO($apertura, $resumen);
    }

    public function cerrarCajaConResumen(string $aperturaId, array $data): CierreCajaResultadoDTO
    {
        $apertura = $this->aperturaRepository->findById($aperturaId);

        if (!$apertura) {
            throw new AperturaNoEncontradaException();
        }

        $dto = new CierreCajaDTO(
            cajaId: $apertura->caja_id,
            subCajaId: $apertura->sub_caja_id,
            montoCierre: $data['monto_cierre'],
            usuarioId: auth()->id(),
            supervisorId: $data['supervisor_id'] ?? null,
            observaciones: $data['observaciones'] ?? null
        );

        return $this->cerrarCajaUseCase->ejecutar($dto);
    }

    public function obtenerDetalleMovimientos(string $aperturaId): array
    {
        $apertura = $this->aperturaRepository->findById($aperturaId);

        if (!$apertura) {
            throw new AperturaNoEncontradaException();
        }

        $movimientos = $this->movimientosQuery->obtenerDetalleCompleto($apertura->id);

        return [
            'movimientos' => $movimientos,
            'total_ingresos' => $movimientos->where('tipo', 'ingreso')->sum('monto'),
            'total_egresos' => $movimientos->where('tipo', 'egreso')->sum('monto'),
        ];
    }

    public function validarSupervisor(string $email, string $password): ?array
    {
        $supervisor = User::where('email', $email)->first();

        if (!$supervisor || !Hash::check($password, $supervisor->password)) {
            return null;
        }

        if (!$supervisor->hasRole('supervisor')) {
            return null;
        }

        return [
            'id' => $supervisor->id,
            'nombre' => $supervisor->name,
            'email' => $supervisor->email,
        ];
    }

    public function obtenerApertura(string $aperturaId)
    {
        return $this->aperturaRepository->findById($aperturaId);
    }
}
