<?php

namespace App\Http\Controllers\Cajas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cajas\AperturarCajaRequest;
use App\Models\AperturaCierreCaja;
use App\Models\CajaPrincipal;
use App\Models\MovimientoCaja;
use App\Models\SubCaja;
use App\Models\TransaccionCaja;
use App\Services\TicketAperturaService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

class AperturaCajaController extends Controller
{
    /**
     * Aperturar una caja principal con distribución a vendedores
     */
    public function aperturar(AperturarCajaRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            try {
                // Obtener user_id del usuario autenticado
                $userId = auth()->id();

                if (!$userId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Usuario no autenticado',
                    ], 401);
                }

                $cajaPrincipalId = $request->validated('caja_principal_id');
                $montoApertura = $request->validated('monto_apertura');
                $conteoBilletes = $request->validated('conteo_billetes_monedas'); // Conteo a nivel de apertura
                $vendedores = $request->validated('vendedores', []); // Array de vendedores
                $enviarTicket = $request->validated('enviar_ticket', true); // Por defecto true
                $emailDestino = $request->validated('email_destino'); // Email opcional

                // 1. Verificar que la caja principal existe
                $cajaPrincipal = CajaPrincipal::find($cajaPrincipalId);
                if (!$cajaPrincipal) {
                    return response()->json([
                        'success' => false,
                        'message' => 'La caja principal no existe',
                    ], 404);
                }

                // 2. Buscar la Caja Chica de esta caja principal
                $cajaChica = SubCaja::where('caja_principal_id', $cajaPrincipalId)
                    ->where('tipo_caja', 'CC')
                    ->first();

                if (!$cajaChica) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No se encontró la Caja Chica para esta caja principal',
                    ], 404);
                }

                // 3. Calcular monto total si hay vendedores
                $montoTotal = $montoApertura;
                if (!empty($vendedores)) {
                    $montoTotal = collect($vendedores)->sum('monto');
                }

                // 4. Verificar si ya hay una apertura activa DEL DÍA ACTUAL
                $hoy = now()->startOfDay();
                $aperturaActiva = AperturaCierreCaja::where('caja_principal_id', $cajaPrincipalId)
                    ->where('estado', 'abierta')
                    ->whereDate('fecha_apertura', $hoy)
                    ->first();

                if ($aperturaActiva) {
                    /** @var AperturaCierreCaja $aperturaActiva */
                    // ✅ Si ya hay apertura, solo agregar el monto a la caja chica
                    $saldoAnterior = $cajaChica->saldo_actual;
                    $cajaChica->saldo_actual += $montoTotal;
                    $cajaChica->save();

                    // Actualizar el monto de apertura acumulado
                    $aperturaActiva->monto_apertura += $montoTotal;
                    $aperturaActiva->save();

                    // Registrar distribución a vendedores
                    if (!empty($vendedores)) {
                        foreach ($vendedores as $vendedor) {
                            // Verificar si ya existe una distribución para este vendedor en esta apertura
                            $distribucionExistente = \App\Models\DistribucionEfectivoVendedor::where('apertura_cierre_caja_id', $aperturaActiva->id)
                                ->where('user_id', $vendedor['user_id'])
                                ->first();

                            if ($distribucionExistente) {
                                // Actualizar distribución existente
                                $distribucionExistente->monto += $vendedor['monto'];
                                // Actualizar conteo si se proporciona (reemplazo simple por ahora)
                                if (isset($vendedor['conteo_billetes_monedas'])) {
                                    $distribucionExistente->conteo_billetes_monedas = $vendedor['conteo_billetes_monedas'];
                                }
                                $distribucionExistente->save();
                            } else {
                                // Crear nueva distribución
                                \App\Models\DistribucionEfectivoVendedor::create([
                                    'apertura_cierre_caja_id' => $aperturaActiva->id,
                                    'user_id' => $vendedor['user_id'],
                                    'monto' => $vendedor['monto'],
                                    'conteo_billetes_monedas' => $vendedor['conteo_billetes_monedas'] ?? null,
                                ]);
                            }
                        }
                    }

                    // Buscar el despliegue de pago "Efectivo"
                    $desplieguePagoEfectivo = \App\Models\DespliegueDePago::where('name', 'Efectivo')
                        ->where('activo', true)
                        ->first();

                    // Registrar transacción
                    TransaccionCaja::create([
                        'id' => (string) Str::ulid(),
                        'sub_caja_id' => $cajaChica->id,
                        'despliegue_pago_id' => $desplieguePagoEfectivo?->id,
                        'tipo_transaccion' => 'ingreso',
                        'monto' => $montoTotal,
                        'saldo_anterior' => $saldoAnterior,
                        'saldo_nuevo' => $cajaChica->saldo_actual,
                        'descripcion' => 'Distribución de efectivo a vendedores',
                        'referencia_id' => $aperturaActiva->id,
                        'referencia_tipo' => 'apertura',
                        'user_id' => $userId,
                        'fecha' => now(),
                    ]);

                    // Registrar movimiento
                    MovimientoCaja::create([
                        'id' => (string) Str::ulid(),
                        'apertura_cierre_id' => $aperturaActiva->id,
                        'caja_principal_id' => $cajaPrincipalId,
                        'sub_caja_id' => $cajaChica->id,
                        'cajero_id' => $userId,
                        'fecha_hora' => now(),
                        'tipo_movimiento' => 'ingreso',
                        'concepto' => "Distribución de efectivo a " . count($vendedores) . " vendedor(es): S/. {$montoTotal}",
                        'saldo_inicial' => $saldoAnterior,
                        'ingreso' => $montoTotal,
                        'salida' => 0,
                        'saldo_final' => $cajaChica->saldo_actual,
                        'estado_caja' => 'abierta',
                    ]);

                    $aperturaActiva->load(['cajaPrincipal', 'subCaja', 'user', 'distribucionesVendedores.vendedor']);

                    // ✅ Enviar email automáticamente si está habilitado (IGUAL QUE EN NUEVA APERTURA)
                    if ($enviarTicket && $emailDestino) {
                        try {
                            $ticketService = app(\App\Services\TicketAperturaService::class);
                            $subject = 'Ticket de Apertura de Caja - ' . Carbon::parse($aperturaActiva->fecha_apertura)->format('d/m/Y H:i');

                            // Obtener solo las distribuciones recién creadas/actualizadas
                            $distribucionesRecientes = [];
                            foreach ($vendedores as $vendedor) {
                                $dist = \App\Models\DistribucionEfectivoVendedor::where('apertura_cierre_caja_id', $aperturaActiva->id)
                                    ->where('user_id', $vendedor['user_id'])
                                    ->with('vendedor')
                                    ->first();

                                if ($dist) {
                                    $distribucionesRecientes[] = [
                                        'vendedor' => $dist->vendedor->name,
                                        'monto' => $vendedor['monto'], // Usar el monto de esta operación, no el acumulado
                                        'conteo_billetes_monedas' => $vendedor['conteo_billetes_monedas'] ?? null,
                                    ];
                                }
                            }

                            // Generar HTML para el cuerpo del email con distribuciones específicas
                            $htmlContent = $ticketService->generarTicketHTMLConDistribuciones(
                                $aperturaActiva,
                                collect($distribucionesRecientes),
                                $montoTotal
                            );

                            // Generar PDF con distribuciones específicas
                            $pdf = $ticketService->generarTicketPDFConDistribuciones(
                                $aperturaActiva,
                                collect($distribucionesRecientes),
                                $montoTotal
                            );
                            $pdfContent = $pdf->output();
                            $pdfName = 'ticket-apertura-' . $aperturaActiva->id . '.pdf';

                            // Enviar email con PDF adjunto
                            Mail::html($htmlContent, function ($message) use ($emailDestino, $subject, $pdfContent, $pdfName) {
                                $message->to($emailDestino)
                                    ->subject($subject)
                                    ->attachData($pdfContent, $pdfName, [
                                        'mime' => 'application/pdf',
                                    ]);
                            });

                            Log::info("Email de apertura con PDF enviado automáticamente (apertura existente)", [
                                'apertura_id' => $aperturaActiva->id,
                                'email' => $emailDestino,
                                'monto_operacion' => $montoTotal,
                            ]);
                        } catch (\Exception $emailError) {
                            // No fallar la apertura si el email falla
                            Log::warning("Error al enviar email de apertura automático (apertura existente): " . $emailError->getMessage(), [
                                'apertura_id' => $aperturaActiva->id,
                                'email' => $emailDestino,
                                'trace' => $emailError->getTraceAsString(),
                            ]);
                        }
                    }

                    return response()->json([
                        'success' => true,
                        'message' => "Efectivo distribuido exitosamente. Nuevo saldo: S/. {$cajaChica->saldo_actual}",
                        'data' => [
                            'id' => $aperturaActiva->id,
                            'apertura_id' => $aperturaActiva->id,
                            'monto_agregado' => number_format($montoTotal, 2, '.', ''),
                            'monto_apertura' => number_format($montoTotal, 2, '.', ''), // Monto de ESTA operación
                            'monto_apertura_total' => number_format($aperturaActiva->monto_apertura, 2, '.', ''), // Monto acumulado total
                            'conteo_apertura_billetes_monedas' => $aperturaActiva->conteo_apertura_billetes_monedas,
                            'fecha_apertura' => $aperturaActiva->fecha_apertura->toIso8601String(),
                            'estado' => $aperturaActiva->estado,
                            'saldo_anterior' => number_format($saldoAnterior, 2, '.', ''),
                            'saldo_nuevo' => number_format($cajaChica->saldo_actual, 2, '.', ''),
                            'vendedores_count' => count($vendedores),
                            // SOLO las distribuciones de esta operación
                            'distribuciones' => collect($vendedores)->map(function ($vendedor) use ($aperturaActiva) {
                                $dist = $aperturaActiva->distribucionesVendedores->firstWhere('user_id', $vendedor['user_id']);
                                return [
                                    'vendedor_id' => $vendedor['user_id'],
                                    'vendedor' => $dist ? $dist->vendedor->name : 'N/A',
                                    'monto' => number_format($vendedor['monto'], 2, '.', ''), // Monto de ESTA operación
                                    'conteo_billetes_monedas' => $vendedor['conteo_billetes_monedas'] ?? null,
                                ];
                            })->values(),
                            'caja_principal' => [
                                'id' => $aperturaActiva->cajaPrincipal->id,
                                'codigo' => $aperturaActiva->cajaPrincipal->codigo,
                                'nombre' => $aperturaActiva->cajaPrincipal->nombre,
                            ],
                            'user' => [
                                'id' => $aperturaActiva->user->id,
                                'name' => $aperturaActiva->user->name,
                                'email' => $aperturaActiva->user->email,
                            ],
                        ],
                    ], 200);
                }

                // 5. Si no hay apertura, crear una nueva
                $apertura = AperturaCierreCaja::create([
                    'caja_principal_id' => $cajaPrincipalId,
                    'sub_caja_id' => $cajaChica->id,
                    'user_id' => $userId,
                    'monto_apertura' => $montoTotal,
                    'conteo_apertura_billetes_monedas' => $conteoBilletes,
                    'fecha_apertura' => now()->startOfDay(), // Guardar al inicio del día (00:00:00)
                    'estado' => 'abierta',
                ]);

                // 6. Registrar distribución a vendedores
                if (!empty($vendedores)) {
                    foreach ($vendedores as $vendedor) {
                        \App\Models\DistribucionEfectivoVendedor::create([
                            'apertura_cierre_caja_id' => $apertura->id,
                            'user_id' => $vendedor['user_id'],
                            'monto' => $vendedor['monto'],
                            'conteo_billetes_monedas' => $vendedor['conteo_billetes_monedas'] ?? null,
                        ]);
                    }
                }

                // 7. Actualizar el saldo de la Caja Chica
                $cajaChica->saldo_actual += $montoTotal;
                $cajaChica->save();

                // 8. Cargar relaciones para la respuesta
                $apertura->load(['cajaPrincipal', 'subCaja', 'user', 'distribucionesVendedores.vendedor']);

                // 9. Enviar email automáticamente si está habilitado
                if ($enviarTicket && $emailDestino) {
                    try {
                        $ticketService = app(\App\Services\TicketAperturaService::class);
                        $subject = 'Ticket de Apertura de Caja - ' . Carbon::parse($apertura->fecha_apertura)->format('d/m/Y H:i');

                        // Preparar distribuciones para el ticket
                        $distribucionesParaTicket = [];
                        foreach ($vendedores as $vendedor) {
                            $dist = \App\Models\DistribucionEfectivoVendedor::where('apertura_cierre_caja_id', $apertura->id)
                                ->where('user_id', $vendedor['user_id'])
                                ->with('vendedor')
                                ->first();

                            if ($dist) {
                                $distribucionesParaTicket[] = [
                                    'vendedor' => $dist->vendedor->name,
                                    'monto' => $vendedor['monto'],
                                    'conteo_billetes_monedas' => $vendedor['conteo_billetes_monedas'] ?? null,
                                ];
                            }
                        }

                        // Generar HTML para el cuerpo del email
                        $htmlContent = $ticketService->generarTicketHTMLConDistribuciones(
                            $apertura,
                            collect($distribucionesParaTicket),
                            $montoTotal
                        );

                        // Generar PDF
                        $pdf = $ticketService->generarTicketPDFConDistribuciones(
                            $apertura,
                            collect($distribucionesParaTicket),
                            $montoTotal
                        );
                        $pdfContent = $pdf->output();
                        $pdfName = 'ticket-apertura-' . $apertura->id . '.pdf';

                        // Enviar email con PDF adjunto
                        Mail::html($htmlContent, function ($message) use ($emailDestino, $subject, $pdfContent, $pdfName) {
                            $message->to($emailDestino)
                                ->subject($subject)
                                ->attachData($pdfContent, $pdfName, [
                                    'mime' => 'application/pdf',
                                ]);
                        });

                        Log::info("Email de apertura con PDF enviado automáticamente", [
                            'apertura_id' => $apertura->id,
                            'email' => $emailDestino,
                            'monto_operacion' => $montoTotal,
                        ]);
                    } catch (\Exception $emailError) {
                        // No fallar la apertura si el email falla
                        Log::warning("Error al enviar email de apertura automático: " . $emailError->getMessage(), [
                            'apertura_id' => $apertura->id,
                            'email' => $emailDestino,
                            'trace' => $emailError->getTraceAsString(),
                        ]);
                    }
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Caja aperturada y efectivo distribuido exitosamente',
                    'data' => [
                        'id' => $apertura->id,
                        'caja_principal_id' => $apertura->caja_principal_id,
                        'sub_caja_id' => $apertura->sub_caja_id,
                        'user_id' => $apertura->user_id,
                        'monto_apertura' => number_format($apertura->monto_apertura, 2, '.', ''),
                        'conteo_apertura_billetes_monedas' => $apertura->conteo_apertura_billetes_monedas,
                        'fecha_apertura' => $apertura->fecha_apertura->toIso8601String(),
                        'estado' => $apertura->estado,
                        'vendedores_count' => count($vendedores),
                        'distribuciones' => collect($vendedores)->map(function ($vendedor) use ($apertura) {
                            $dist = $apertura->distribucionesVendedores->firstWhere('user_id', $vendedor['user_id']);
                            return [
                                'vendedor_id' => $vendedor['user_id'],
                                'vendedor' => $dist ? $dist->vendedor->name : 'N/A',
                                'monto' => number_format($vendedor['monto'], 2, '.', ''),
                                'conteo_billetes_monedas' => $vendedor['conteo_billetes_monedas'] ?? null,
                            ];
                        })->values(),
                        'caja_principal' => [
                            'id' => $apertura->cajaPrincipal->id,
                            'codigo' => $apertura->cajaPrincipal->codigo,
                            'nombre' => $apertura->cajaPrincipal->nombre,
                        ],
                        'sub_caja' => [
                            'id' => $apertura->subCaja->id,
                            'codigo' => $apertura->subCaja->codigo,
                            'nombre' => $apertura->subCaja->nombre,
                            'saldo_actual' => number_format($apertura->subCaja->saldo_actual, 2, '.', ''),
                        ],
                        'user' => [
                            'id' => $apertura->user->id,
                            'name' => $apertura->user->name,
                            'email' => $apertura->user->email,
                        ],
                    ],
                ], 200);
            } catch (Exception $e) {
                Log::error('Error al aperturar caja: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'message' => 'Error al aperturar la caja: ' . $e->getMessage(),
                ], 500);
            }
        });
    }

    /**
     * Consultar si una caja tiene apertura activa DEL DÍA ACTUAL
     */
    public function consultaApertura(int $cajaPrincipalId): JsonResponse
    {
        try {
            $hoy = now()->startOfDay();
            $apertura = AperturaCierreCaja::where('caja_principal_id', $cajaPrincipalId)
                ->where('estado', 'abierta')
                ->whereDate('fecha_apertura', $hoy)
                ->with(['cajaPrincipal', 'subCaja', 'user'])
                ->first();

            if (!$apertura) {
                return response()->json([
                    'success' => true,
                    'message' => 'No hay apertura activa para hoy',
                    'data' => null,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Apertura activa encontrada',
                'data' => [
                    'id' => $apertura->id,
                    'caja_principal_id' => $apertura->caja_principal_id,
                    'monto_apertura' => number_format($apertura->monto_apertura, 2, '.', ''),
                    'fecha_apertura' => $apertura->fecha_apertura->toIso8601String(),
                    'estado' => $apertura->estado,
                    'caja_principal' => [
                        'id' => $apertura->cajaPrincipal->id,
                        'codigo' => $apertura->cajaPrincipal->codigo,
                        'nombre' => $apertura->cajaPrincipal->nombre,
                    ],
                    'sub_caja' => [
                        'id' => $apertura->subCaja->id,
                        'saldo_actual' => number_format($apertura->subCaja->saldo_actual, 2, '.', ''),
                    ],
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al consultar apertura: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Listar historial de aperturas/cierres
     */
    public function historial(): JsonResponse
    {
        try {
            $userId = auth()->id();
            $perPage = request()->query('per_page', 15);

            // Construir la consulta base
            $query = AperturaCierreCaja::with(['cajaPrincipal', 'subCaja', 'user']);

            // Si hay usuario autenticado, filtrar por sus cajas
            if ($userId) {
                $query->whereHas('cajaPrincipal', function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                });
            }

            $historial = $query->orderBy('fecha_apertura', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $historial->map(function ($apertura) {
                    return [
                        'id' => $apertura->id,
                        'caja_principal_id' => $apertura->caja_principal_id,
                        'monto_apertura' => number_format($apertura->monto_apertura, 2, '.', ''),
                        'monto_cierre' => $apertura->monto_cierre ? number_format($apertura->monto_cierre, 2, '.', '') : null,
                        'fecha_apertura' => $apertura->fecha_apertura->toIso8601String(),
                        'fecha_cierre' => $apertura->fecha_cierre?->toIso8601String(),
                        'estado' => $apertura->estado,
                        'estado_cierre' => $apertura->estado_cierre,
                        'caja_principal' => [
                            'id' => $apertura->cajaPrincipal->id,
                            'codigo' => $apertura->cajaPrincipal->codigo,
                            'nombre' => $apertura->cajaPrincipal->nombre,
                        ],
                        'sub_caja' => [
                            'id' => $apertura->subCaja->id,
                            'codigo' => $apertura->subCaja->codigo,
                            'nombre' => $apertura->subCaja->nombre,
                        ],
                        'user' => [
                            'id' => $apertura->user->id,
                            'name' => $apertura->user->name,
                        ],
                    ];
                }),
                'pagination' => [
                    'total' => $historial->total(),
                    'per_page' => $historial->perPage(),
                    'current_page' => $historial->currentPage(),
                    'last_page' => $historial->lastPage(),
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener historial: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Listar TODAS las aperturas/cierres (para administradores)
     */
    public function historialTodas(): JsonResponse
    {
        try {
            $perPage = request()->query('per_page', 50);
            $cajaPrincipalId = request()->query('caja_principal_id');
            $fechaInicio = request()->query('fecha_inicio');
            $fechaFin = request()->query('fecha_fin');
            $userId = request()->query('user_id');

            $query = AperturaCierreCaja::with(['cajaPrincipal', 'subCaja', 'user', 'distribucionesVendedores.vendedor']);

            if ($cajaPrincipalId) {
                $query->where('caja_principal_id', $cajaPrincipalId);
            }

            if ($fechaInicio) {
                $query->whereDate('fecha_apertura', '>=', $fechaInicio);
            }

            if ($fechaFin) {
                $query->whereDate('fecha_apertura', '<=', $fechaFin);
            }

            if ($userId) {
                $query->where(function ($q) use ($userId) {
                    $q->where('user_id', $userId)
                        ->orWhereHas('distribucionesVendedores', function ($q2) use ($userId) {
                            $q2->where('user_id', $userId);
                        });
                });
            }

            $historial = $query->orderBy('fecha_apertura', 'desc')
                ->paginate($perPage);

            $rows = collect();

            foreach ($historial->items() as $apertura) {
                $distribuciones = $apertura->distribucionesVendedores ?? collect();

                if ($distribuciones->isEmpty()) {
                    $rows->push($this->formatAperturaRow($apertura, null));
                } else {
                    foreach ($distribuciones as $dist) {
                        $rows->push($this->formatAperturaRow($apertura, $dist));
                    }
                }
            }

            return response()->json([
                'success' => true,
                'data' => $rows,
                'pagination' => [
                    'total' => $historial->total(),
                    'per_page' => $historial->perPage(),
                    'current_page' => $historial->currentPage(),
                    'last_page' => $historial->lastPage(),
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener historial: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function formatAperturaRow($apertura, $distribucion = null): array
    {
        $vendedor = $distribucion?->vendedor;

        return [
            'id' => $apertura->id,
            'caja_principal_id' => $apertura->caja_principal_id,
            'user_id' => $apertura->user_id, // Usuario que abrió
            'vendedor_id' => $distribucion?->user_id ?? $apertura->user_id, // Usuario vendedor asignado
            'vendedor' => $vendedor ? [
                'id' => $vendedor->id,
                'name' => $vendedor->name,
                'email' => $vendedor->email,
            ] : ($apertura->user ? [
                'id' => $apertura->user->id,
                'name' => $apertura->user->name,
                'email' => $apertura->user->email,
            ] : null),
            'monto_apertura' => $distribucion ? number_format($distribucion->monto, 2, '.', '') : number_format($apertura->monto_apertura, 2, '.', ''), // Monto de este vendedor
            'monto_cierre' => $apertura->monto_cierre ? number_format($apertura->monto_cierre, 2, '.', '') : null,
            'fecha_apertura' => $apertura->fecha_apertura->toIso8601String(),
            'fecha_cierre' => $apertura->fecha_cierre?->toIso8601String(),
            'estado' => $apertura->estado,
            'estado_cierre' => $apertura->estado_cierre,
            'caja_principal' => [
                'id' => $apertura->cajaPrincipal->id,
                'codigo' => $apertura->cajaPrincipal->codigo,
                'nombre' => $apertura->cajaPrincipal->nombre,
            ],
            'sub_caja' => [
                'id' => $apertura->subCaja->id,
                'codigo' => $apertura->subCaja->codigo,
                'nombre' => $apertura->subCaja->nombre,
            ],
            'user' => [
                'id' => $apertura->user->id,
                'name' => $apertura->user->name,
            ],
            // Si hay que abrir modal, enviamos las distribuciones
            'distribuciones_vendedores' => $apertura->distribucionesVendedores->map(function ($dist) {
                return [
                    'vendedor_id' => $dist->user_id,
                    'vendedor' => $dist->vendedor->name,
                    'monto' => number_format($dist->monto, 2, '.', ''),
                    'conteo_billetes_monedas' => $dist->conteo_billetes_monedas,
                ];
            }),
        ];
    }

    /**
     * Enviar ticket de apertura por correo con PDF adjunto
     */
    public function enviarTicketEmail(string $id, Request $request): JsonResponse
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'pdf' => 'required|file|mimes:pdf|max:10240', // Max 10MB
            ]);

            // Obtener la apertura
            $apertura = AperturaCierreCaja::with(['cajaPrincipal', 'subCaja', 'user', 'distribucionesVendedores.vendedor'])
                ->findOrFail($id);

            // Verificar permisos: puede enviar si es el usuario de la apertura, si es uno de los vendedores, o si es admin
            $esUsuarioApertura = $apertura->user_id === auth()->id();
            $esVendedorDistribuido = $apertura->distribucionesVendedores->contains('user_id', auth()->id());
            $esAdmin = auth()->user()->hasRole('admin') || auth()->user()->hasRole('administrador');

            if (!$esUsuarioApertura && !$esVendedorDistribuido && !$esAdmin) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para enviar este ticket',
                ], 403);
            }

            // Determinar el asunto
            $subject = 'Ticket de Apertura de Caja - ' . Carbon::parse($apertura->fecha_apertura)->format('d/m/Y');

            // Determinar si debe filtrar por usuario
            // Si es admin, muestra todo. Si es vendedor, solo su distribución
            $userId = null;
            if (!$esAdmin && $esVendedorDistribuido) {
                $userId = auth()->id();
            }

            // Obtener el archivo PDF
            $pdfFile = $request->file('pdf');
            $pdfPath = $pdfFile->getRealPath();
            $pdfName = 'ticket-apertura-' . $id . '.pdf';

            // Renderizar la vista HTML con filtro de usuario
            $ticketService = app(\App\Services\TicketAperturaService::class);
            $htmlContent = $ticketService->generarTicketHTML($apertura, $userId);

            // Enviar el correo con el PDF adjunto (igual que el cierre)
            Mail::html($htmlContent, function ($message) use ($request, $subject, $pdfPath, $pdfName) {
                $message->to($request->email)
                    ->subject($subject)
                    ->attach($pdfPath, [
                        'as' => $pdfName,
                        'mime' => 'application/pdf',
                    ]);
            });

            // Registrar el envío
            Log::info("Ticket de apertura enviado por correo", [
                'apertura_id' => $id,
                'email' => $request->email,
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Ticket enviado exitosamente por correo electrónico',
            ]);
        } catch (\Exception $e) {
            Log::error('Error al enviar ticket de apertura por correo: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al enviar el ticket: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * TEST: Enviar email simple sin PDF para verificar configuración
     */
    public function testEnviarEmail(string $id): JsonResponse
    {
        try {
            // Obtener la apertura más reciente
            $apertura = AperturaCierreCaja::with(['cajaPrincipal', 'subCaja', 'user', 'distribucionesVendedores.vendedor'])
                ->findOrFail($id);

            $subject = 'TEST - Ticket de Apertura de Caja - ' . Carbon::parse($apertura->fecha_apertura)->format('d/m/Y H:i');

            // Renderizar la vista HTML
            $htmlContent = view('emails.ticket-apertura-simple', [
                'apertura' => $apertura,
                'subject' => $subject
            ])->render();

            // Enviar el correo SIN PDF adjunto (solo para test)
            $emailDestino = 'victorcanchari61@gmail.com';

            Mail::html($htmlContent, function ($message) use ($emailDestino, $subject) {
                $message->to($emailDestino)
                    ->subject($subject);
            });

            Log::info("TEST: Email de apertura enviado", [
                'apertura_id' => $id,
                'email' => $emailDestino,
                'fecha' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => "Email de prueba enviado exitosamente a {$emailDestino}",
                'data' => [
                    'apertura_id' => $id,
                    'email' => $emailDestino,
                    'subject' => $subject,
                    'fecha_envio' => now()->toIso8601String(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('TEST: Error al enviar email: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al enviar el email de prueba: ' . $e->getMessage(),
                'error_details' => $e->getTraceAsString(),
            ], 500);
        }
    }
}
