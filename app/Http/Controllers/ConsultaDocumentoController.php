<?php

namespace App\Http\Controllers;

use App\Models\Venta;
use App\Models\GuiaRemision;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ConsultaDocumentoController extends Controller
{
    /**
     * Buscar documento por serie, correlativo y tipo.
     * No requiere autenticación.
     */
    public function buscar(Request $request): JsonResponse
    {
        $serie = $request->query('serie');
        $correlativo = $request->query('correlativo');
        $tipoDocumento = $request->query('tipo_documento');
        $montoParam = $request->query('monto');

        if (!$serie || !$correlativo || !$tipoDocumento) {
            return response()->json([
                'error' => ['message' => 'Debe ingresar serie, correlativo y tipo de documento'],
            ], 400);
        }

        if (!$montoParam) {
            return response()->json([
                'error' => ['message' => 'Debe ingresar el monto neto del comprobante'],
            ], 400);
        }

        $montoIngresado = (float) str_replace(',', '', $montoParam);
        $numero = ltrim($correlativo, '0') ?: '0';

        // Buscar en ventas (factura, boleta, nota de venta)
        if (in_array($tipoDocumento, ['01', '03', 'NV'])) {
            $venta = Venta::with('comprobanteElectronico', 'despliegueDePagoVentas')
                ->where('serie', $serie)
                ->where('numero', $numero)
                ->first();

            if ($venta) {
                $ce = $venta->comprobanteElectronico;
                $montoReal = $ce
                    ? (float) ($ce->importe_total ?? 0)
                    : (float) $venta->despliegueDePagoVentas->sum('monto');

                if (abs($montoIngresado - $montoReal) > 0.01) {
                    return response()->json([
                        'error' => ['message' => 'El monto ingresado no coincide con el comprobante'],
                    ], 403);
                }

                return response()->json([
                    'data' => ['tipo' => 'venta', 'id' => $venta->id],
                ]);
            }
        }

        // Buscar en guías de remisión
        if ($tipoDocumento === '09') {
            $guia = GuiaRemision::where('serie', $serie)
                ->where('numero', $numero)
                ->first();

            if ($guia) {
                if (abs($montoIngresado - 0) > 0.01) {
                    return response()->json([
                        'error' => ['message' => 'El monto ingresado no coincide'],
                    ], 403);
                }

                return response()->json([
                    'data' => ['tipo' => 'guia', 'id' => $guia->id],
                ]);
            }
        }

        return response()->json([
            'error' => ['message' => 'No se encontró el documento con los datos proporcionados'],
        ], 404);
    }

    /**
     * Consulta pública de documento (venta o guía de remisión).
     * No requiere autenticación.
     * Requiere el parámetro ?monto= para validar y mostrar el documento.
     */
    public function show(string $tipo, string $id, Request $request): JsonResponse
    {
        $monto = $request->query('monto');

        if ($tipo === 'venta') {
            return $this->consultaVenta($id, $monto);
        }

        if ($tipo === 'guia') {
            return $this->consultaGuia($id, $monto);
        }

        return response()->json(['error' => ['message' => 'Tipo de documento no válido']], 400);
    }

    private function consultaVenta(string $id, ?string $montoParam): JsonResponse
    {
        $venta = Venta::with([
            'cliente:id,nombres,apellidos,razon_social,numero_documento',
            'user:id,name',
            'user.empresa:id,ruc,razon_social,nombre_comercial,direccion,telefono,email,logo',
            'productosPorAlmacen.unidadesDerivadas.unidadDerivadaInmutable',
            'productosPorAlmacen.productoAlmacen.producto:id,name,cod_producto',
            'productosPorAlmacen.productoAlmacen.producto.marca:id,name',
            'despliegueDePagoVentas.despliegueDePago:id,name',
            'comprobanteElectronico',
        ])->find($id);

        if (!$venta) {
            return response()->json(['error' => ['message' => 'Documento no encontrado']], 404);
        }

        // Validar monto neto obligatorio
        if (!$montoParam) {
            return response()->json(['error' => ['message' => 'Debe ingresar el monto neto del comprobante']], 400);
        }

        $montoIngresado = (float) str_replace(',', '', $montoParam);
        $ce = $venta->comprobanteElectronico;
        $montoReal = $ce
            ? (float) ($ce->importe_total ?? 0)
            : (float) $venta->despliegueDePagoVentas->sum('monto');

        if (abs($montoIngresado - $montoReal) > 0.01) {
            Log::warning("Consulta documento: monto no coincide para venta #{$id}. Ingresado: {$montoIngresado}, Real: {$montoReal}");
            return response()->json(['error' => ['message' => 'El monto ingresado no coincide con el comprobante']], 403);
        }

        $empresa = $venta->user?->empresa;
        $cliente = $venta->cliente;
        $clienteNombre = $cliente?->razon_social
            ?: trim(($cliente?->nombres ?? '') . ' ' . ($cliente?->apellidos ?? ''))
            ?: 'CLIENTES VARIOS';

        $productos = [];
        foreach ($venta->productosPorAlmacen as $pav) {
            $producto = $pav->productoAlmacen?->producto;
            foreach ($pav->unidadesDerivadas as $ud) {
                $productos[] = [
                    'nombre' => $producto?->name ?? '-',
                    'codigo' => $producto?->cod_producto ?? '-',
                    'marca' => $producto?->marca?->name ?? '-',
                    'cantidad' => (float) $ud->cantidad,
                    'unidad' => $ud->unidadDerivadaInmutable?->name ?? '-',
                    'precio' => (float) $ud->precio,
                    'descuento' => (float) $ud->descuento,
                    'subtotal' => (float) $ud->cantidad * ((float) $ud->precio + (float) $ud->recargo),
                ];
            }
        }

        $metodosPago = $venta->despliegueDePagoVentas->map(fn($dp) => [
            'nombre' => $dp->despliegueDePago?->name ?? '-',
            'monto' => (float) $dp->monto,
        ]);

        $rawTotal = collect($productos)->sum('subtotal');
        $totalDescuento = collect($productos)->sum('descuento');
        // Prices stored with IGV included — back out instead of adding again.
        $total = $rawTotal - $totalDescuento;
        $subtotal = round($total / 1.18, 2);
        $igv = round($total - $subtotal, 2);

        // Si hay comprobante electrónico, usar esos totales (más precisos)
        $ce = $venta->comprobanteElectronico;
        if ($ce) {
            $subtotal = (float) ($ce->total_gravadas ?? $subtotal);
            $igv = (float) ($ce->total_igv ?? $igv);
            $total = (float) ($ce->importe_total ?? $total);
        }

        $tipoDocLabel = match ($venta->tipo_documento->value ?? '') {
            '01' => 'FACTURA ELECTRÓNICA',
            '03' => 'BOLETA DE VENTA ELECTRÓNICA',
            default => 'NOTA DE VENTA',
        };

        return response()->json([
            'data' => [
                'tipo' => 'venta',
                'tipo_documento_label' => $tipoDocLabel,
                'serie' => $venta->serie,
                'numero' => $venta->numero,
                'numero_completo' => $venta->serie . '-' . str_pad($venta->numero, 8, '0', STR_PAD_LEFT),
                'fecha' => $venta->fecha?->format('d/m/Y'),
                'forma_pago' => $venta->forma_de_pago->value ?? '-',
                'estado' => $venta->estado_de_venta->value ?? '-',
                'cliente' => [
                    'nombre' => $clienteNombre,
                    'documento' => $cliente?->numero_documento ?? '-',
                ],
                'vendedor' => $venta->user?->name ?? '-',
                'empresa' => [
                    'razon_social' => $empresa?->razon_social ?? '-',
                    'ruc' => $empresa?->ruc ?? '-',
                    'direccion' => $empresa?->direccion ?? '-',
                    'telefono' => $empresa?->telefono ?? '-',
                    'email' => $empresa?->email ?? '-',
                    'logo' => $empresa?->logo ?? null,
                ],
                'productos' => $productos,
                'metodos_pago' => $metodosPago,
                'totales' => [
                    'subtotal' => round($subtotal, 2),
                    'descuento' => round($totalDescuento, 2),
                    'igv' => round($igv, 2),
                    'total' => round($total, 2),
                ],
                'observaciones' => $venta->descripcion ?: null,
                'pdf_url' => url("/api/pdf/venta/{$id}"),
                'xml_url' => $ce && $ce->tiene_xml ? url("/api/facturas/comprobante/{$ce->id}/xml") : null,
            ],
        ]);
    }

    private function consultaGuia(string $id, ?string $montoParam): JsonResponse
    {
        $guia = GuiaRemision::with([
            'cliente:id,nombres,apellidos,razon_social,numero_documento',
            'user:id,name',
            'user.empresa:id,ruc,razon_social,nombre_comercial,direccion,telefono,email,logo',
            'detalles.producto:id,name,cod_producto',
            'motivoTraslado',
            'chofer:id,name,dni',
        ])->find($id);

        if (!$guia) {
            return response()->json(['error' => ['message' => 'Documento no encontrado']], 404);
        }

        // Para guías, si se proporciona monto validamos, si no se omite
        if ($montoParam !== null) {
            $montoIngresado = (float) str_replace(',', '', $montoParam);
            $montoReal = 0.0; // guías no tienen monto neto
            if (abs($montoIngresado - $montoReal) > 0.01) {
                return response()->json(['error' => ['message' => 'El monto ingresado no coincide']], 403);
            }
        }

        $empresa = $guia->user?->empresa;
        $cliente = $guia->cliente;
        $clienteNombre = $cliente?->razon_social
            ?: trim(($cliente?->nombres ?? '') . ' ' . ($cliente?->apellidos ?? ''))
            ?: 'VARIOS';

        $detalles = $guia->detalles->map(fn($d) => [
            'nombre' => $d->producto?->name ?? $d->descripcion ?? '-',
            'codigo' => $d->producto?->cod_producto ?? '-',
            'cantidad' => (float) $d->cantidad,
            'unidad' => $d->unidad ?? '-',
            'peso' => (float) ($d->peso ?? 0),
        ]);

        return response()->json([
            'data' => [
                'tipo' => 'guia',
                'tipo_documento_label' => 'GUÍA DE REMISIÓN ELECTRÓNICA',
                'numero_completo' => $guia->numero_completo,
                'fecha_emision' => $guia->fecha_emision?->format('d/m/Y'),
                'fecha_traslado' => $guia->fecha_traslado?->format('d/m/Y'),
                'motivo_traslado' => $guia->motivoTraslado?->descripcion ?? '-',
                'modalidad' => $guia->modalidad_transporte ?? '-',
                'punto_partida' => $guia->punto_partida ?? '-',
                'punto_llegada' => $guia->punto_llegada ?? '-',
                'vehiculo_placa' => $guia->vehiculo_placa ?? '-',
                'chofer' => $guia->chofer?->name ?? '-',
                'cliente' => [
                    'nombre' => $clienteNombre,
                    'documento' => $cliente?->numero_documento ?? '-',
                ],
                'empresa' => [
                    'razon_social' => $empresa?->razon_social ?? '-',
                    'ruc' => $empresa?->ruc ?? '-',
                    'direccion' => $empresa?->direccion ?? '-',
                    'telefono' => $empresa?->telefono ?? '-',
                    'email' => $empresa?->email ?? '-',
                    'logo' => $empresa?->logo ?? null,
                ],
                'detalles' => $detalles,
                'peso_total' => $detalles->sum('peso'),
                'observaciones' => $guia->observaciones ?: null,
                'pdf_url' => url("/api/pdf/guia/{$id}"),
            ],
        ]);
    }
}
