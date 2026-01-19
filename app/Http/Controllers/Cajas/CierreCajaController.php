<?php

namespace App\Http\Controllers\Cajas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cajas\CerrarCajaRequest;
use App\Http\Requests\Cajas\ValidarSupervisorRequest;
use App\Models\AperturaCierreCaja;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Exception;

class CierreCajaController extends Controller
{
    const LIMITE_DIFERENCIA = 10.00; // Límite de diferencia sin supervisor

    /**
     * Obtener la caja activa del vendedor actual
     */
    public function obtenerCajaActiva(): JsonResponse
    {
        try {
            // Obtener userId del token de autenticación
            $userId = auth()->id();
            
            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado',
                ], 401);
            }
            
            $apertura = AperturaCierreCaja::where('user_id', $userId)
                ->where('estado', 'abierta')
                ->with(['cajaPrincipal', 'subCaja', 'user'])
                ->first();

            if (!$apertura) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes una caja abierta',
                ], 404);
            }

            // Calcular resumen de movimientos
            $resumen = $this->calcularResumenCaja($apertura->id);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $apertura->id,
                    'caja_principal_id' => $apertura->caja_principal_id,
                    'sub_caja_id' => $apertura->sub_caja_id,
                    'user_id' => $apertura->user_id,
                    'monto_apertura' => number_format($apertura->monto_apertura, 2, '.', ''),
                    'fecha_apertura' => $apertura->fecha_apertura->toIso8601String(),
                    'estado' => $apertura->estado,
                    'caja_principal' => [
                        'id' => $apertura->cajaPrincipal->id,
                        'codigo' => $apertura->cajaPrincipal->codigo,
                        'nombre' => $apertura->cajaPrincipal->nombre,
                    ],
                    'sub_caja_chica' => [
                        'id' => $apertura->subCaja->id,
                        'codigo' => $apertura->subCaja->codigo,
                        'nombre' => $apertura->subCaja->nombre,
                        'saldo_actual' => number_format($apertura->subCaja->saldo_actual, 2, '.', ''),
                    ],
                    'resumen' => $resumen,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener caja activa: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cerrar caja
     */
    public function cerrarCaja(string $id, CerrarCajaRequest $request): JsonResponse
    {
        try {
            return DB::transaction(function () use ($id, $request) {
                $apertura = AperturaCierreCaja::with(['cajaPrincipal', 'subCaja', 'user'])
                    ->findOrFail($id);

                // Validar que la caja esté abierta
                if ($apertura->estaCerrada()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Esta caja ya está cerrada',
                    ], 400);
                }

                // Validar que el usuario sea el dueño o admin
                $userId = auth()->id();
                $isAdmin = auth()->user()->hasRole('admin');
                
                if ($apertura->user_id !== $userId && !$isAdmin) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No tienes permiso para cerrar esta caja',
                    ], 403);
                }

                $validated = $request->validated();
                
                // Calcular resumen y diferencias
                $resumen = $this->calcularResumenCaja($apertura->id);
                $efectivoEsperado = $resumen['total_efectivo_esperado'];
                $efectivoContado = $validated['monto_cierre_efectivo'];
                $diferenciaEfectivo = $efectivoContado - $efectivoEsperado;

                $totalEsperado = $resumen['total_en_caja'];
                $totalContado = $efectivoContado + $validated['total_cuentas'];
                $diferenciaTotal = $totalContado - $totalEsperado;

                // Validar si requiere supervisor
                $requiereSupervisor = abs($diferenciaTotal) > self::LIMITE_DIFERENCIA;
                $forzarCierre = $validated['forzar_cierre'] ?? false;

                if ($forzarCierre && !isset($validated['supervisor_id'])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Forzar cierre requiere autorización de supervisor',
                    ], 400);
                }

                if ($requiereSupervisor && !isset($validated['supervisor_id'])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Las diferencias superan el límite permitido. Se requiere autorización de supervisor.',
                        'data' => [
                            'diferencia' => abs($diferenciaTotal),
                            'limite' => self::LIMITE_DIFERENCIA,
                            'requiere_supervisor' => true,
                        ],
                    ], 400);
                }

                // Actualizar apertura con datos de cierre
                $apertura->update([
                    'monto_cierre' => $totalContado,
                    'monto_cierre_efectivo' => $efectivoContado,
                    'monto_cierre_cuentas' => $validated['total_cuentas'],
                    'conteo_billetes_monedas' => $validated['conteo_billetes_monedas'] ?? null,
                    'conceptos_adicionales' => $validated['conceptos_adicionales'] ?? null,
                    'comentarios' => $validated['comentarios'] ?? null,
                    'supervisor_id' => $validated['supervisor_id'] ?? null,
                    'diferencia_efectivo' => $diferenciaEfectivo,
                    'diferencia_total' => $diferenciaTotal,
                    'forzar_cierre' => $forzarCierre,
                    'fecha_cierre' => now(),
                    'estado' => 'cerrada',
                ]);

                // Cargar supervisor si existe
                if ($apertura->supervisor_id) {
                    $apertura->load('supervisor');
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Caja cerrada exitosamente',
                    'data' => [
                        'id' => $apertura->id,
                        'caja_principal_id' => $apertura->caja_principal_id,
                        'sub_caja_id' => $apertura->sub_caja_id,
                        'user_id' => $apertura->user_id,
                        'monto_apertura' => number_format($apertura->monto_apertura, 2, '.', ''),
                        'fecha_apertura' => $apertura->fecha_apertura->toIso8601String(),
                        'monto_cierre' => number_format($apertura->monto_cierre, 2, '.', ''),
                        'fecha_cierre' => $apertura->fecha_cierre->toIso8601String(),
                        'estado' => $apertura->estado,
                        'diferencias' => [
                            'efectivo_esperado' => number_format($efectivoEsperado, 2, '.', ''),
                            'efectivo_contado' => number_format($efectivoContado, 2, '.', ''),
                            'diferencia_efectivo' => number_format($diferenciaEfectivo, 2, '.', ''),
                            'total_esperado' => number_format($totalEsperado, 2, '.', ''),
                            'total_contado' => number_format($totalContado, 2, '.', ''),
                            'diferencia_total' => number_format($diferenciaTotal, 2, '.', ''),
                            'sobrante' => number_format(max(0, $diferenciaTotal), 2, '.', ''),
                            'faltante' => number_format(max(0, -$diferenciaTotal), 2, '.', ''),
                        ],
                        'supervisor' => $apertura->supervisor ? [
                            'id' => $apertura->supervisor->id,
                            'name' => $apertura->supervisor->name,
                        ] : null,
                        'comentarios' => $apertura->comentarios,
                    ],
                ]);
            });
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cerrar caja: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener resumen de movimientos de la caja
     */
    public function obtenerResumenMovimientos(string $id): JsonResponse
    {
        try {
            $apertura = AperturaCierreCaja::findOrFail($id);
            
            // TODO: Implementar cuando existan las tablas de ventas, ingresos, egresos
            // Por ahora retornamos estructura vacía
            
            $resumen = $this->calcularResumenCaja($id);

            return response()->json([
                'success' => true,
                'data' => [
                    'ventas' => [],
                    'ingresos' => [],
                    'egresos' => [],
                    'anulaciones' => [],
                    'totales_por_metodo' => [
                        'efectivo' => number_format($resumen['total_efectivo_esperado'], 2, '.', ''),
                        'tarjeta' => number_format($resumen['total_tarjetas'], 2, '.', ''),
                        'yape' => number_format($resumen['total_yape'], 2, '.', ''),
                        'izipay' => number_format($resumen['total_izipay'], 2, '.', ''),
                        'transferencia' => number_format($resumen['total_transferencias'], 2, '.', ''),
                        'otros' => number_format($resumen['total_otros'], 2, '.', ''),
                    ],
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener resumen: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Validar supervisor
     */
    public function validarSupervisor(ValidarSupervisorRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            
            $user = User::where('email', $validated['email'])->first();
            
            if (!$user || !Hash::check($validated['password'], $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Credenciales inválidas',
                ], 401);
            }

            // Verificar que tenga permisos de supervisor (admin o rol específico)
            $puedeAutorizar = $user->hasRole('admin') || $user->hasRole('supervisor');

            if (!$puedeAutorizar) {
                return response()->json([
                    'success' => false,
                    'message' => 'El usuario no tiene permisos de supervisor',
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'supervisor_id' => $user->id,
                    'name' => $user->name,
                    'puede_autorizar' => true,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al validar supervisor: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Calcular resumen de la caja
     * TODO: Implementar cuando existan las tablas de ventas
     */
    private function calcularResumenCaja(string $aperturaId): array
    {
        // Por ahora retornamos valores de ejemplo
        // Esto debe calcularse desde las tablas de ventas, ingresos, egresos
        
        return [
            'total_ventas' => 500.00,
            'total_cobros' => 500.00,
            'total_tarjetas' => 200.00,
            'total_yape' => 150.00,
            'total_izipay' => 50.00,
            'total_transferencias' => 0.00,
            'total_otros' => 0.00,
            'total_efectivo_esperado' => 100.00,
            'total_otros_ingresos' => 50.00,
            'total_anulados' => 0.00,
            'total_devoluciones' => 0.00,
            'total_gastos' => 0.00,
            'total_pagos' => 0.00,
            'resumen_ventas' => 500.00,
            'resumen_ingresos' => 50.00,
            'resumen_egresos' => 0.00,
            'total_en_caja' => 850.00,
        ];
    }
}
