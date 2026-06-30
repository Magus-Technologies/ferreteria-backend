<?php

namespace App\UseCases\CierreCaja;

use App\DTOs\CierreCaja\CierreCajaDTO;
use App\DTOs\CierreCaja\CierreCajaResultadoDTO;
use App\DTOs\CierreCaja\DiferenciasCajaDTO;
use App\DTOs\CierreCaja\ResumenCajaDTO;
use App\Exceptions\CajaYaCerradaException;
use App\Exceptions\DiferenciaCajaExcedidaException;
use App\Models\User;
use App\Models\ArqueoDiario;
use App\Repositories\Interfaces\AperturaCierreCajaRepositoryInterface;
use App\Services\CierreCaja\CalculadorResumenCaja;
use App\Services\CierreCaja\ValidadorSupervisorCaja;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class CerrarCajaUseCase
{
    public function __construct(
        private AperturaCierreCajaRepositoryInterface $aperturaRepository,
        private CalculadorResumenCaja $calculadorResumen,
        private ValidadorSupervisorCaja $validadorSupervisor
    ) {}

    /**
     * Ejecutar arqueo diario de caja
     * 
     * Este proceso registra un arqueo (reporte) de las operaciones del día,
     * pero NO cierra la caja realmente. La caja permanece 'abierta'.
     * 
     * Permite generar reportes de las transacciones realizadas durante la sesión.
     */
    public function ejecutar(CierreCajaDTO $dto): CierreCajaResultadoDTO
    {
        return DB::transaction(function () use ($dto) {
            // 1. Obtener apertura activa
            $apertura = $this->aperturaRepository->obtenerAperturaActiva(
                $dto->cajaId,
                $dto->subCajaId
            );

            if (!$apertura) {
                throw new CajaYaCerradaException();
            }

            // 2. Actualizar monto de cierre primero
            $apertura->monto_cierre = $dto->montoCierre;

            // 3. Calcular resumen DESDE la apertura HASTA el cierre (no por día)
            $resumen = $this->calculadorResumen->calcular($apertura, false);

            // 4. Validar supervisor si es necesario
            $supervisorValidado = false;
            if ($dto->supervisorId && $dto->supervisorPassword) {
                $supervisor = User::find($dto->supervisorId);

                // Verificar que el usuario existe y es supervisor
                if (!$supervisor || !$supervisor->es_supervisor) {
                    throw new \Exception('El usuario no es un supervisor válido');
                }

                // Verificar que tiene contraseña de supervisor configurada
                if (!$supervisor->supervisor_password) {
                    throw new \Exception('El supervisor no tiene contraseña configurada');
                }

                // Validar la contraseña de supervisor
                if (!Hash::check($dto->supervisorPassword, $supervisor->supervisor_password)) {
                    throw new \Exception('Contraseña de supervisor incorrecta');
                }

                $supervisorValidado = true;
            }

            // 5. Validar diferencia (Comentado a pedido del usuario: el faltante ahora genera deuda)
            // $this->validarDiferencia($resumen, $dto);

            // 6. Calcular diferencias ANTES de actualizar
            $diferencia = $resumen->diferencia ?? 0;
            $sobrante = $diferencia > 0 ? $diferencia : 0;
            $faltante = $diferencia < 0 ? abs($diferencia) : 0;

            // 7. Registrar datos del arqueo diario en tabla separada
            $arqueoDiario = ArqueoDiario::create([
                'apertura_cierre_caja_id' => $apertura->id,
                'user_id' => $dto->usuarioId ?? $apertura->user_id,
                'fecha_arqueo' => now()->toDateString(),
                'monto_cierre' => $dto->montoCierre,
                'monto_cierre_efectivo' => $dto->montoCierreEfectivo,
                'monto_cierre_cuentas' => $dto->montoCierreCuentas,
                'conteo_billetes_monedas' => $dto->conteoBilletesMonedas ?? null,
                'diferencia_efectivo' => $diferencia,
                'diferencia_total' => $diferencia,
                'comentarios' => $dto->observaciones,
                'supervisor_id_validador' => $dto->supervisorId ?? null,
                'supervisor_validado' => $supervisorValidado,
                'estado_cierre' => $supervisorValidado ? 'aprobado' : 'pendiente',
                'email_reporte' => $dto->emailReporte ?? null,
                'whatsapp_reporte' => $dto->whatsappReporte ?? null,
                'resumen_snapshot' => $resumen->toArray(),
            ]);

            // 7.5 Registrar deuda si hay faltante
            if ($diferencia < 0) {
                $montoDeuda = abs($diferencia);
                \App\Models\DeudaPersonal::create([
                    'user_id' => $dto->usuarioId ?? $apertura->user_id,
                    'arqueo_diario_id' => $arqueoDiario->id,
                    'monto' => $montoDeuda,
                    'monto_original' => $montoDeuda,
                    'monto_abonado' => 0,
                    'saldo_pendiente' => $montoDeuda,
                    'estado' => 'pendiente',
                    'observaciones' => 'Faltante en caja registrado durante el cierre del ' . now()->format('d/m/Y H:i'),
                ]);
            }

            // 8. Actualizar apertura con datos minimos (referencia al ultimo arqueo)
            $apertura->update([
                'monto_cierre' => $dto->montoCierre,
                'monto_cierre_efectivo' => $dto->montoCierreEfectivo,
                'monto_cierre_cuentas' => $dto->montoCierreCuentas,
                'conteo_billetes_monedas' => $dto->conteoBilletesMonedas ? json_encode($dto->conteoBilletesMonedas) : null,
                'comentarios' => $dto->observaciones,
                'supervisor_id_validador' => $dto->supervisorId ?? null,
                'supervisor_validado' => $supervisorValidado,
                'estado_cierre' => $supervisorValidado ? 'aprobado' : 'pendiente',
                'fecha_ultimo_arqueo' => now(),
                'fecha_cierre' => now(),
                'estado' => 'cerrada',
                'diferencia_efectivo' => $diferencia,
                'diferencia_total' => $diferencia,
                'monto_dejar_apertura' => $dto->montoDejarApertura,
            ]);

            // 9. Crear DTO de resultado

            $diferencias = new DiferenciasCajaDTO(
                efectivoEsperado: $resumen->montoEsperado,
                efectivoContado: $resumen->montoCierre ?? 0,
                diferenciaEfectivo: $diferencia,
                totalEsperado: $resumen->montoEsperado,
                totalContado: $resumen->montoCierre ?? 0,
                diferenciaTotal: $diferencia,
                sobrante: $sobrante,
                faltante: $faltante
            );

            return new CierreCajaResultadoDTO(
                apertura: $apertura->fresh(),
                diferencias: $diferencias,
                resumen: $resumen->toArray()
            );
        });
    }

    private function validarDiferencia(ResumenCajaDTO $resumen, CierreCajaDTO $dto): void
    {
        $diferencia = abs($resumen->diferencia);
        $limiteDiferencia = config('caja.limite_diferencia', 5);
        $limiteMaximo = config('caja.limite_maximo_diferencia', 50);

        // Si la diferencia supera el límite básico, requiere supervisor
        if ($diferencia > $limiteDiferencia) {
            // Si no hay supervisor, lanzar excepción
            if (!$dto->supervisorId) {
                throw new \App\Exceptions\SupervisorRequeridoException(
                    null,
                    $diferencia,
                    $limiteDiferencia
                );
            }

            // Validar que el supervisor es válido
            $this->validadorSupervisor->validar($dto->supervisorId);

            // Si hay supervisor validado, permitir cualquier diferencia
            // El supervisor asume la responsabilidad de la diferencia
            return;
        }

        // Si la diferencia es menor al límite básico, no requiere supervisor
        // pero aún así validamos que no exceda el límite máximo sin supervisor
        if ($diferencia > $limiteMaximo && !$dto->supervisorId) {
            throw new DiferenciaCajaExcedidaException($diferencia);
        }
    }
}




