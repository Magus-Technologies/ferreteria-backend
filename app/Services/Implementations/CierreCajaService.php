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
use App\UseCases\CierreCaja\ReCerrarCajaUseCase;
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
        private ReCerrarCajaUseCase $reCerrarCajaUseCase,
        private CalculadorResumenCaja $calculadorResumen,
        private MovimientosCajaQuery $movimientosQuery
    ) {}

    public function obtenerCajaActivaConResumen(string $userId): CajaActivaDTO
    {

        $apertura = $this->aperturaRepository->findCajaActiva($userId);

        if (!$apertura) {
            throw new AperturaNoEncontradaException();
        }

        // La consulta de caja activa es DESDE la apertura HASTA el cierre (no por día).
        $resumen = $this->calculadorResumen->calcular($apertura, false);

        return new CajaActivaDTO($apertura, $resumen);
    }

    public function cerrarCajaConResumen(string $aperturaId, array $data): CierreCajaResultadoDTO
    {
        $apertura = $this->aperturaRepository->findById($aperturaId);

        if (!$apertura) {
            throw new AperturaNoEncontradaException();
        }

        $dto = new CierreCajaDTO(
            cajaId: $apertura->caja_principal_id,
            subCajaId: $apertura->sub_caja_id,
            montoCierre: $data['monto_cierre_efectivo'] + ($data['total_cuentas'] ?? 0),
            usuarioId: auth()->id(),
            montoCierreEfectivo: $data['monto_cierre_efectivo'] ?? null,
            montoCierreCuentas: $data['total_cuentas'] ?? null,
            conteoBilletesMonedas: $data['conteo_billetes_monedas'] ?? null,
            supervisorId: $data['supervisor_id'] ?? null,
            supervisorPassword: $data['supervisor_password'] ?? null,
            emailReporte: $data['email_reporte'] ?? null,
            whatsappReporte: $data['whatsapp_reporte'] ?? null,
            observaciones: $data['comentarios'] ?? null,
            montoDejarApertura: isset($data['monto_dejar_apertura']) ? (float) $data['monto_dejar_apertura'] : null
        );

        return $this->cerrarCajaUseCase->ejecutar($dto);
    }

    public function reCerrarCajaConResumen(string $aperturaId, array $data): CierreCajaResultadoDTO
    {
        $apertura = $this->aperturaRepository->findById($aperturaId);

        if (!$apertura) {
            throw new AperturaNoEncontradaException();
        }

        $dto = new CierreCajaDTO(
            cajaId: $apertura->caja_principal_id,
            subCajaId: $apertura->sub_caja_id,
            montoCierre: $data['monto_cierre_efectivo'] + ($data['total_cuentas'] ?? 0),
            usuarioId: auth()->id(),
            montoCierreEfectivo: $data['monto_cierre_efectivo'] ?? null,
            montoCierreCuentas: $data['total_cuentas'] ?? null,
            conteoBilletesMonedas: $data['conteo_billetes_monedas'] ?? null,
            supervisorId: $data['supervisor_id'] ?? null,
            supervisorPassword: $data['supervisor_password'] ?? null,
            emailReporte: $data['email_reporte'] ?? null,
            whatsappReporte: $data['whatsapp_reporte'] ?? null,
            observaciones: $data['comentarios'] ?? null,
            montoDejarApertura: isset($data['monto_dejar_apertura']) ? (float) $data['monto_dejar_apertura'] : null
        );

        // Pasar el aperturaId al UseCase para que use findById en lugar de obtenerAperturaActiva
        return $this->reCerrarCajaUseCase->ejecutar($dto, $aperturaId);
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

    public function validarSupervisor(string $supervisorId, string $password): ?array
    {
        $supervisor = User::find($supervisorId);

        if (!$supervisor) {
            return null;
        }

        // Verificar que sea supervisor
        if (!$supervisor->es_supervisor) {
            return null;
        }

        // Verificar que tenga contraseña de supervisor configurada
        if (!$supervisor->supervisor_password) {
            return null;
        }

        // Validar la contraseña de supervisor (NO la contraseña normal)
        if (!Hash::check($password, $supervisor->supervisor_password)) {
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

    public function recalcularCierre(string $aperturaId): array
    {
        $apertura = $this->aperturaRepository->findById($aperturaId);

        if (!$apertura) {
            throw new AperturaNoEncontradaException();
        }

        // Recalcular el resumen usando el calculador
        $resumen = $this->calculadorResumen->calcular($apertura);

        // Actualizar el monto_cierre con el monto esperado recalculado
        $apertura->monto_cierre = $resumen->montoEsperado;
        $apertura->save();

        return [
            'id' => $apertura->id,
            'monto_cierre_anterior' => $apertura->getOriginal('monto_cierre'),
            'monto_cierre_nuevo' => $apertura->monto_cierre,
            'resumen' => $resumen->toArray(),
        ];
    }
}
