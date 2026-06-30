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
                // Parte del monto que proviene de efectivo asignado de otro cierre.
                $montoAsignado = (float) $request->validated('monto_asignado', 0);

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

                // Guardia: nunca aperturar con monto 0
                if ($montoTotal <= 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'El monto de apertura debe ser mayor a S/. 0.00',
                    ], 422);
                }

                // 4. Bloquear si la caja ya tiene una apertura ABIERTA (sin cerrar).
                //    Regla de negocio (estricta): mientras exista una apertura abierta
                //    NO se puede aperturar otra; primero hay que cerrarla (o deshacerla
                //    si fue un error). En el día se apertura y cierra varias veces, pero
                //    siempre en secuencia apertura -> cierre -> apertura (cola). No se
                //    filtra por fecha: una apertura olvidada también bloquea hasta que
                //    se cierre (el comando `cajas:cerrar-olvidadas` cierra las de días
                //    anteriores).
                $aperturaActiva = AperturaCierreCaja::where('caja_principal_id', $cajaPrincipalId)
                    ->where('estado', 'abierta')
                    ->whereNull('fecha_cierre')
                    ->first();

                if ($aperturaActiva) {
                    /** @var AperturaCierreCaja $aperturaActiva */
                    return response()->json([
                        'success' => false,
                        'message' => 'Esta caja ya tiene una apertura abierta. Debe cerrarla antes de aperturar nuevamente.',
                        'data' => [
                            'apertura_id' => $aperturaActiva->id,
                            'fecha_apertura' => $aperturaActiva->fecha_apertura->toIso8601String(),
                        ],
                    ], 422);
                }

                // 5. Si no hay apertura, crear una nueva
                $apertura = AperturaCierreCaja::create([
                    'caja_principal_id' => $cajaPrincipalId,
                    'sub_caja_id' => $cajaChica->id,
                    'user_id' => $userId,
                    'monto_apertura' => $montoTotal,
                    'monto_apertura_asignado' => $montoAsignado,
                    'conteo_apertura_billetes_monedas' => $conteoBilletes,
                    'fecha_apertura' => now(),
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

                    } catch (\Exception $emailError) {
                        // No fallar la apertura si el email falla
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
     * Anular / deshacer una apertura ABIERTA (cuando se aperturó por error).
     *
     * Revierte el efectivo agregado a la caja chica y elimina las distribuciones,
     * dejando la caja libre para una nueva apertura. Solo se permite si la apertura
     * está abierta y NO tiene actividad posterior (ventas/movimientos o traslados).
     */
    public function anular(string $id): JsonResponse
    {
        return DB::transaction(function () use ($id) {
            try {
                $apertura = AperturaCierreCaja::find($id);

                if (!$apertura) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Apertura no encontrada',
                    ], 404);
                }

                // Solo se puede deshacer una apertura que sigue abierta
                if ($apertura->estado !== 'abierta') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Solo se puede deshacer una apertura que está abierta.',
                    ], 422);
                }

                // Guardia: no permitir deshacer si ya hubo actividad en la caja
                $tieneMovimientos = MovimientoCaja::where('apertura_cierre_id', $apertura->id)->exists();
                $tieneTraslados = \App\Models\TrasladoBoveda::where('apertura_cierre_caja_id', $apertura->id)->exists();

                if ($tieneMovimientos || $tieneTraslados) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No se puede deshacer: la caja ya tiene movimientos registrados. Realice el cierre normal.',
                    ], 422);
                }

                // Revertir el efectivo que la apertura sumó a la caja chica
                $cajaChica = SubCaja::find($apertura->sub_caja_id);
                if ($cajaChica) {
                    $cajaChica->saldo_actual = max(0, (float) $cajaChica->saldo_actual - (float) $apertura->monto_apertura);
                    $cajaChica->save();
                }

                // Eliminar distribuciones de efectivo y la apertura
                \App\Models\DistribucionEfectivoVendedor::where('apertura_cierre_caja_id', $apertura->id)->delete();
                $apertura->delete();

                return response()->json([
                    'success' => true,
                    'message' => 'Apertura anulada exitosamente. La caja quedó disponible para una nueva apertura.',
                ], 200);
            } catch (Exception $e) {
                Log::error('Error al anular apertura: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'message' => 'Error al anular la apertura: ' . $e->getMessage(),
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
            // Desglose: parte asignada (de otro cierre) y parte manual de ESTA apertura
            'monto_apertura_total' => number_format($apertura->monto_apertura, 2, '.', ''),
            'monto_apertura_asignado' => number_format((float) ($apertura->monto_apertura_asignado ?? 0), 2, '.', ''),
            'monto_apertura_manual' => number_format(((float) $apertura->monto_apertura) - ((float) ($apertura->monto_apertura_asignado ?? 0)), 2, '.', ''),
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
