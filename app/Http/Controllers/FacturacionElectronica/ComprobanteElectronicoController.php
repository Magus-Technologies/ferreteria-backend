<?php

namespace App\Http\Controllers\FacturacionElectronica;

use App\Http\Controllers\Controller;
use App\Http\Resources\FacturacionElectronica\ComprobanteElectronicoResource;
use App\Models\ComprobanteElectronico;
use App\Models\Venta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComprobanteElectronicoController extends Controller
{
    /**
     * Buscar comprobantes electrónicos por serie-número o cliente
     */
    public function buscar(Request $request): JsonResponse
    {
        try {
            $query = $request->input('query');
            $tipo = $request->input('tipo'); // 01=Factura, 03=Boleta
            $limit = $request->input('limit', 50);
            $paraNotaDebito = $request->input('para_nota_debito', false); // Nuevo parámetro
            $fechaDesde = $request->input('fecha_desde');
            $fechaHasta = $request->input('fecha_hasta');

            // Permitir búsqueda por SOLO rango de fechas (sin texto), como el
            // modal de cotizaciones. Solo devolver vacío si no hay NI texto NI fechas.
            if (empty($query) && empty($fechaDesde) && empty($fechaHasta)) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                ]);
            }

            $comprobantes = ComprobanteElectronico::with([
                'cliente.direcciones',
                'detalles.producto.unidadMedida',
                'detalles.producto.marca',
                'detalles.unidadDerivada',
            ])
                ->whereIn('estado_sunat', ['ACEPTADO', 'ACEPTADO_CON_OBSERVACIONES'])
                // ✅ FILTRO: Solo Facturas (01) y Boletas (03), NO Notas de Crédito (07) ni Débito (08)
                ->whereIn('tipo_comprobante', ['01', '03'])
                // Texto opcional: si no viene, se filtra solo por fechas/tipo.
                ->when($query, function ($q) use ($query) {
                    // Buscar por serie-número concatenado (ej: B001-6, F001-123)
                    if (str_contains($query, '-')) {
                        [$serie, $numero] = explode('-', $query, 2);
                        $q->where(function ($subQ) use ($serie, $numero) {
                            // ✅ Coincidencia EXACTA para evitar resultados parciales no deseados
                            $subQ->where('serie', $serie)
                                ->where('correlativo', (int) $numero);
                        });
                    } else {
                        // Buscar por serie, número o cliente (nombres, apellidos, razón social o documento)
                        $q->where(function ($subQ) use ($query) {
                            $subQ->where('serie', 'like', "%{$query}%")
                                ->orWhere('correlativo', 'like', "%{$query}%")
                                ->orWhereHas('cliente', function ($q2) use ($query) {
                                    $q2->where('nombres', 'like', "%{$query}%")
                                        ->orWhere('apellidos', 'like', "%{$query}%")
                                        ->orWhere('razon_social', 'like', "%{$query}%")
                                        ->orWhere('numero_documento', 'like', "%{$query}%");
                                });
                        });
                    }
                })
                ->when($fechaDesde, fn($q) => $q->whereDate('fecha_emision', '>=', $fechaDesde))
                ->when($fechaHasta, fn($q) => $q->whereDate('fecha_emision', '<=', $fechaHasta))
                ->when($tipo, fn($q) => $q->where('tipo_comprobante', $tipo))
                // ✅ NUEVO FILTRO: Excluir comprobantes con nota de débito aceptada
                ->when($paraNotaDebito, function ($q) {
$q->whereDoesntHave('venta.notasDebito', function ($subQ) {
                            $subQ->whereHas('comprobanteElectronico', function ($compQ) {
                                $compQ->whereIn('estado_sunat', ['ACEPTADO', 'ACEPTADO_CON_OBSERVACIONES']);
                            });
                        });
                })
                ->orderBy('fecha_emision', 'desc')
                ->orderBy('correlativo', 'desc')
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'data' => ComprobanteElectronicoResource::collection($comprobantes),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al buscar comprobantes',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener detalles completos de un comprobante
     */
    public function show(string $id): JsonResponse
    {
        try {
            $comprobante = ComprobanteElectronico::with([
                'cliente.direcciones',
                'detalles.producto.unidadMedida',
                'detalles.producto.marca',
                'detalles.unidadDerivada',
                'venta',
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => new ComprobanteElectronicoResource($comprobante),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Comprobante no encontrado',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Obtiene ayuda contextual para motivos de nota según SUNAT
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getAyudaMotivos(Request $request): JsonResponse
    {
        $tipo = $request->query('tipo'); // 'NC' o 'ND'

        if (!in_array($tipo, ['NC', 'ND'])) {
            return response()->json([
                'success' => false,
                'message' => 'Tipo inválido. Use NC o ND'
            ], 400);
        }

        $motivos = \App\Models\MotivoNota::where('tipo', $tipo)
            ->where('estado', 1)
            ->orderBy('codigo_sunat')
            ->get()
            ->map(function ($motivo) {
                return [
                    'id' => $motivo->id,
                    'codigo' => $motivo->codigo_sunat,
                    'descripcion' => $motivo->descripcion,
                    'tipo' => $motivo->tipo,
                    'ayuda' => $this->getAyudaTexto($motivo->codigo_sunat, $motivo->tipo),
                    'ejemplo' => $this->getEjemplo($motivo->codigo_sunat, $motivo->tipo),
                    'requiere_anulacion_total' => in_array($motivo->codigo_sunat, ['01', '02', '06']),
                    'requiere_descripcion_detallada' => $motivo->codigo_sunat === '10',
                    'permite_multiples_notas' => $this->permiteMultiplesNotas($motivo->codigo_sunat, $motivo->tipo),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $motivos
        ]);
    }

    /**
     * Obtiene texto de ayuda según código SUNAT
     */
    private function getAyudaTexto(string $codigo, string $tipo): string
    {
        if ($tipo === 'NC') {
            $ayudas = [
                '01' => '⚠️ ANULACIÓN TOTAL - La operación nunca debió realizarse. Cancela TODO el comprobante.',
                '02' => '⚠️ ANULACIÓN TOTAL - RUC incorrecto. Cancela TODO y emite nuevo comprobante.',
                '03' => '📝 CORRECCIÓN - Solo texto/descripción. NO afecta montos.',
                '04' => '💰 DESCUENTO GLOBAL - Aplicado al total del comprobante.',
                '05' => '💰 DESCUENTO POR ÍTEM - Aplicado a productos específicos.',
                '06' => '⚠️ DEVOLUCIÓN TOTAL - Cliente devuelve TODOS los productos.',
                '07' => '📦 DEVOLUCIÓN PARCIAL - Cliente devuelve ALGUNOS productos.',
                '08' => '🎁 BONIFICACIÓN - Productos entregados sin costo.',
                '09' => '💵 AJUSTE DE VALOR - Corrección de precios o valores.',
                '10' => '📋 OTROS CONCEPTOS - Casos especiales (requiere descripción detallada mínimo 20 caracteres).',
            ];
        } else {
            $ayudas = [
                '01' => '⏰ INTERESES POR MORA - Cliente pagó fuera de plazo.',
                '02' => '💵 AUMENTO EN EL VALOR - Error en precio, monto menor al real.',
                '03' => '⚖️ PENALIDADES - Multas o recargos contractuales.',
                '10' => '📋 OTROS CONCEPTOS - Casos especiales (requiere descripción detallada mínimo 20 caracteres).',
            ];
        }

        return $ayudas[$codigo] ?? 'Sin descripción disponible';
    }

    /**
     * Obtiene ejemplo de uso según código SUNAT
     */
    private function getEjemplo(string $codigo, string $tipo): array
    {
        if ($tipo === 'NC') {
            $ejemplos = [
                '01' => [
                    'caso' => 'Se emitió factura por error a cliente que canceló antes de recibir productos.',
                    'efecto' => 'Monto NC = Monto Factura (anulación total)',
                    'calculo' => 'Factura: S/ 1,000 → NC: S/ 1,000'
                ],
                '04' => [
                    'caso' => 'Cliente compró por S/ 10,000 y se le otorga 10% descuento por volumen.',
                    'efecto' => 'Monto NC = % del total',
                    'calculo' => 'Factura: S/ 10,000 → NC: S/ 1,000 (10%)'
                ],
                '07' => [
                    'caso' => 'Cliente devuelve 2 de 5 productos comprados.',
                    'efecto' => 'Reducción parcial del comprobante',
                    'calculo' => 'Factura: S/ 5,000 (5 productos) → NC: S/ 2,000 (2 productos)'
                ],
            ];
        } else {
            $ejemplos = [
                '01' => [
                    'caso' => 'Cliente pagó 30 días después del vencimiento.',
                    'efecto' => 'Incremento por intereses',
                    'calculo' => 'Factura: S/ 10,000 → ND: S/ 300 (3% interés)'
                ],
                '02' => [
                    'caso' => 'Se facturó S/ 1,000 pero el precio correcto era S/ 1,200.',
                    'efecto' => 'Incremento directo del valor',
                    'calculo' => 'Factura: S/ 1,000 → ND: S/ 200'
                ],
            ];
        }

        return $ejemplos[$codigo] ?? [
            'caso' => 'Consulte con su contador',
            'efecto' => 'Varía según el caso',
            'calculo' => 'Depende de la situación'
        ];
    }

    /**
     * Verifica si el motivo permite múltiples notas
     */
    private function permiteMultiplesNotas(string $codigo, string $tipo): bool
    {
        if ($tipo === 'NC') {
            return in_array($codigo, ['04', '05', '07', '09']);
        } else {
            return in_array($codigo, ['03', '10']);
        }
    }

    /**
     * Obtener comprobantes pendientes que están a 1 día de vencer (Alertas)
     */
    public function pendientesAlerta(): JsonResponse
    {
        try {
            $now = \Carbon\Carbon::now();

            // Facturas (01) y notas (07, 08): Límite 3 días. Alertamos desde el día 1 hasta el día 3.
            $fechaFacturaAlertaInicio = $now->copy()->subDays(3)->toDateString();
            $fechaFacturaAlertaFin = $now->copy()->subDays(1)->toDateString();

            // Boletas (03): Límite 7 días. Alertamos desde el día 5 hasta el día 7.
            $fechaBoletaAlertaInicio = $now->copy()->subDays(7)->toDateString();
            $fechaBoletaAlertaFin = $now->copy()->subDays(5)->toDateString();

            $pendientes = ComprobanteElectronico::with(['cliente'])
                ->where('estado_sunat', 'PENDIENTE')
                ->whereNull('fecha_envio_sunat')
                ->where(function ($query) use ($fechaFacturaAlertaInicio, $fechaFacturaAlertaFin, $fechaBoletaAlertaInicio, $fechaBoletaAlertaFin) {
                    $query->where(function ($q) use ($fechaFacturaAlertaInicio, $fechaFacturaAlertaFin) {
                        $q->whereIn('tipo_comprobante', ['01', '07', '08'])
                            ->whereBetween('fecha_emision', [$fechaFacturaAlertaInicio, $fechaFacturaAlertaFin]);
                    })->orWhere(function ($q) use ($fechaBoletaAlertaInicio, $fechaBoletaAlertaFin) {
                        $q->where('tipo_comprobante', '03')
                            ->whereBetween('fecha_emision', [$fechaBoletaAlertaInicio, $fechaBoletaAlertaFin]);
                    });
                })
                ->orderBy('fecha_emision', 'asc')
                ->get();

            // Ventas facturables (01/03) que NUNCA llegaron a tener fila en
            // comprobantes_electronicos — la generación falló al guardar
            // (ej. microservicio SUNAT caído) y quedó silenciada en el log,
            // sin ninguna alerta visible. Antes esta lista solo mostraba
            // comprobantes YA generados y pendientes de ENVIAR; estos casos
            // ni siquiera llegaron a esa etapa. Mismo criterio de ventana
            // que arriba (evita alertar ventas de hace segundos que capaz
            // están recién guardándose) más un margen post-vencimiento para
            // que no desaparezcan solas sin que nadie se entere.
            $sinGenerar = Venta::whereIn('tipo_documento', ['01', '03'])
                ->whereNotIn('estado_de_venta', ['an', 'ee'])
                ->whereDoesntHave('comprobanteElectronico')
                ->where('fecha', '<=', $now->copy()->subMinutes(10))
                ->where('fecha', '>=', $now->copy()->subDays(15))
                ->with(['cliente', 'productosPorAlmacen.unidadesDerivadas'])
                ->orderBy('fecha', 'asc')
                ->get()
                ->map(function (Venta $venta) {
                    $total = $venta->productosPorAlmacen->sum(
                        fn ($ppa) => $ppa->unidadesDerivadas->sum(
                            fn ($ud) => (float) $ud->precio * (float) $ud->cantidad + (float) ($ud->recargo ?? 0)
                        )
                    );
                    $tipoDoc = $venta->tipo_documento instanceof \BackedEnum
                        ? $venta->tipo_documento->value
                        : $venta->tipo_documento;

                    return [
                        'id' => "venta-{$venta->id}",
                        'venta_id' => $venta->id,
                        'tipo_comprobante' => $tipoDoc,
                        'tipo_comprobante_nombre' => $tipoDoc === '01' ? 'Factura' : 'Boleta',
                        'serie' => $venta->serie,
                        'numero' => $venta->numero,
                        'correlativo' => $venta->numero,
                        'serie_numero' => "{$venta->serie}-{$venta->numero}",
                        'fecha_emision' => $venta->fecha->format('Y-m-d'),
                        'cliente_razon_social' => $venta->cliente?->razon_social
                            ?? trim(($venta->cliente?->nombres ?? '') . ' ' . ($venta->cliente?->apellidos ?? ''))
                            ?: 'Cliente',
                        'total' => round($total, 2),
                        'importe_total' => round($total, 2),
                        // Conceptualmente estos casos también están
                        // "pendientes" (de generarse, no de enviarse) — se
                        // usa el mismo valor para que el front no necesite
                        // un estado nuevo, y se distinguen por `sin_generar`.
                        'estado_sunat' => 'PENDIENTE',
                        // El front distingue este caso ("nunca se generó") del
                        // normal ("generado, falta enviar") para mostrar un
                        // mensaje y una acción distintos.
                        'sin_generar' => true,
                    ];
                });

            $data = array_merge(
                ComprobanteElectronicoResource::collection($pendientes)->resolve(),
                $sinGenerar->all()
            );

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener alertas de comprobantes',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
