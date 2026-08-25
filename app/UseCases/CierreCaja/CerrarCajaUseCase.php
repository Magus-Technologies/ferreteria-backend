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
    public function ejecutar(CierreCajaDTO $dto, ?string $aperturaId = null): CierreCajaResultadoDTO
    {
        return DB::transaction(function () use ($dto, $aperturaId) {
            // 1. Obtener la apertura a cerrar. Puede haber VARIAS aperturas abiertas
            //    de la misma caja (una por usuario), así que cuando llega el id
            //    explícito se cierra exactamente esa y no "la primera de la caja".
            $apertura = $aperturaId
                ? $this->aperturaRepository->findById($aperturaId)
                : $this->aperturaRepository->obtenerAperturaActiva(
                    $dto->cajaId,
                    $dto->subCajaId
                );

            if (!$apertura || $apertura->estado !== 'abierta') {
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

            // 5. Cerrar con SOBRANTE está permitido sin supervisor: la diferencia
            // queda registrada en el arqueo y el front ya avisa "vas a cerrar con
            // S/ X de más" (más la alerta si coincide con el traslado a bóveda).
            $this->validarDiferencia($resumen, $dto);

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

    /**
     * Un SOBRANTE grande casi nunca es plata de más: es un monto mal tipeado.
     * El caso que motivó esto fue un cierre por S/16,700 cinco minutos después de
     * trasladar S/16,700 a la bóveda — el cajero declaró lo que acababa de sacar
     * en vez de lo que quedaba (S/6,599.20), y quedó grabado un sobrante de
     * S/10,100.80 que nunca existió.
     *
     * Solo aplica a sobrantes. El FALTANTE sigue pasando sin fricción porque ya
     * tiene su propio camino: genera una `DeudaPersonal` a nombre del cajero.
     * Esa fue la razón por la que en su momento se desactivó esta validación
     * entera — pero desactivarla también dejó sin control el otro lado, que no
     * genera deuda ni nada: simplemente se guarda.
     */
    private function validarDiferencia(ResumenCajaDTO $resumen, CierreCajaDTO $dto): void
    {
        // Cerrar con SOBRANTE está permitido sin supervisor (decisión del negocio,
        // 24/08/2026): la diferencia queda registrada en el arqueo y el front ya
        // muestra el aviso "vas a cerrar con S/ X de más" — más la alerta especial
        // si el monto coincide con el traslado a bóveda (el caso del docblock).
        // El freno anterior (sobrante > S/5 exigía supervisor) bloqueaba cierres
        // legítimos con sobrantes chicos reales.
    }
}




