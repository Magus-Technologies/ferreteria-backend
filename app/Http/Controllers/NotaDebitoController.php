<?php

namespace App\Http\Controllers;

use App\Http\Requests\NotaDebitoRequest;
use App\Services\Interfaces\NotaDebitoServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Controlador de Nota de Débito Electrónica
 * 
 * REQUISITOS LEGALES SUNAT (Perú):
 * 
 * 1. SUSTENTO DEL MOTIVO:
 *    - Código 01: Intereses por mora
 *    - Código 02: Aumento en el valor (ej: subió precio después de facturar)
 *    - Código 03: Penalidades / otros conceptos
 *    - Código 10: Ajustes de operaciones de exportación
 *    - Código 11: Ajustes afectos al IVAP
 *    - La descripción debe ser coherente con el código y descriptiva
 * 
 * 2. DOCUMENTO DE REFERENCIA:
 *    - Debe existir en SUNAT y no estar anulado
 *    - Cliente (RUC/DNI) debe ser idéntico al documento original
 *    - Moneda debe coincidir con el documento original
 * 
 * 3. PLAZOS LEGALES:
 *    - Envío a SUNAT: Máximo 3 días calendario desde la emisión
 *    - Después del plazo, el documento será rechazado
 * 
 * 4. EFECTO TRIBUTARIO:
 *    - Emisor: Aumenta débito fiscal (paga más IGV) y aumenta ingreso gravable
 *    - Adquirente: Puede usar como crédito fiscal si se emite en el mismo mes
 * 
 * 5. ARCHIVOS REQUERIDOS:
 *    - XML: Archivo con firma digital
 *    - CDR: Constancia de Recepción de SUNAT
 *    - PDF: Representación impresa con código QR
 * 
 * 6. DETALLE DE ITEMS:
 *    - Cantidad: Puede ser "0" para ajustes de valor (intereses)
 *    - Unidad: Debe ser del catálogo SUNAT (NIU, KGM, etc)
 *    - IGV: Desglose exacto según tipo de afectación (10=Gravado, 20=Exonerado, 30=Inafecto)
 */
class NotaDebitoController extends Controller
{
    private NotaDebitoServiceInterface $notaDebitoService;

    public function __construct(NotaDebitoServiceInterface $notaDebitoService)
    {
        $this->notaDebitoService = $notaDebitoService;
    }

    /**
     * Generar Nota de Débito
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'serie' => 'required|string|max:4',
            'numero' => 'required|integer',
            'fecha' => 'required|date',
            'tipo_doc_afectado' => 'required|in:01,03', // 01=Factura, 03=Boleta
            'num_doc_afectado' => 'required|string',
            'cod_motivo' => 'required|string|max:2',
            'des_motivo' => 'required|string',
            'tipo_moneda' => 'nullable|string|in:PEN,USD',
            'cliente' => 'required|array',
            'cliente.tipo_doc' => 'required|in:1,6', // 1=DNI, 6=RUC
            'cliente.num_doc' => 'required|string',
            'cliente.razon_social' => 'required|string',
            'cliente.direccion' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.codigo' => 'required|string',
            'items.*.unidad' => 'required|string',
            'items.*.cantidad' => 'required|numeric',
            'items.*.descripcion' => 'required|string',
            'items.*.valor_unitario' => 'required|numeric',
            'items.*.precio_unitario' => 'required|numeric',
        ]);

        try {
            // Calcular totales con desglose de IGV según tipo de afectación
            $mtoOperGravadas = 0;
            $mtoOperExoneradas = 0;
            $mtoOperInafectas = 0;
            $mtoIGV = 0;
            
            foreach ($validated['items'] as &$item) {
                $tipoAfectacion = $item['tipo_afectacion_igv'] ?? '10'; // Default: Gravado
                $valorVenta = $item['cantidad'] * $item['valor_unitario'];
                
                // Calcular IGV según tipo de afectación
                if ($tipoAfectacion === '10') {
                    // Gravado - Operación Onerosa
                    $igv = $valorVenta * 0.18;
                    $mtoOperGravadas += $valorVenta;
                    $mtoIGV += $igv;
                } elseif ($tipoAfectacion === '20') {
                    // Exonerado
                    $igv = 0;
                    $mtoOperExoneradas += $valorVenta;
                } else {
                    // Inafecto
                    $igv = 0;
                    $mtoOperInafectas += $valorVenta;
                }
                
                $item['valor_venta'] = round($valorVenta, 2);
                $item['igv'] = round($igv, 2);
                $item['mto_base_igv'] = round($valorVenta, 2);
                $item['tipo_afectacion_igv'] = $tipoAfectacion;
            }
            
            $validated['mto_oper_gravadas'] = round($mtoOperGravadas, 2);
            $validated['mto_oper_exoneradas'] = round($mtoOperExoneradas, 2);
            $validated['mto_oper_inafectas'] = round($mtoOperInafectas, 2);
            $validated['mto_igv'] = round($mtoIGV, 2);
            $validated['total'] = round($mtoOperGravadas + $mtoOperExoneradas + $mtoOperInafectas + $mtoIGV, 2);
            $validated['monto_en_letras'] = $this->numeroALetras($validated['total'], $validated['tipo_moneda']);

            // Generar nota de débito
            $result = $this->notaDebitoService->generarNotaDebito($validated);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => $result['modo'] ?? null ? 
                        'Nota de débito generada en MODO SIMULACIÓN (no enviada a SUNAT)' : 
                        'Nota de débito generada y enviada a SUNAT correctamente',
                    'data' => [
                        'hash' => $result['hash'],
                        'xml' => base64_encode($result['xml']),
                        'cdr' => $result['cdr'] ? [
                            'code' => is_array($result['cdr']) ? $result['cdr']['code'] : $result['cdr']->getCode(),
                            'description' => is_array($result['cdr']) ? $result['cdr']['description'] : $result['cdr']->getDescription(),
                        ] : null,
                        'modo_simulacion' => $result['modo'] ?? false,
                    ],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Error al generar nota de débito',
                'error' => $result['error'] ? $result['error']->getMessage() : 'Error desconocido',
            ], 400);

        } catch (\Exception $e) {
            \Log::error('Error al generar nota de débito', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Consultar estado de Nota de Débito
     */
    public function consultarEstado(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ruc' => 'required|string',
            'tipo_doc' => 'required|string',
            'serie' => 'required|string',
            'numero' => 'required|string',
        ]);

        try {
            $result = $this->notaDebitoService->consultarCDR(
                $validated['ruc'],
                $validated['tipo_doc'],
                $validated['serie'],
                $validated['numero']
            );

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'code' => $result['cdr']->getCode(),
                        'description' => $result['cdr']->getDescription(),
                    ],
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => $result['error'] ? $result['error']->getMessage() : 'Error desconocido',
            ], 400);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Ver XML de Nota de Débito (solo visualizar en navegador)
     */
    public function verXml(string $serie, string $numero)
    {
        try {
            $filename = "nota-debito-{$serie}-{$numero}.xml";
            $filepath = storage_path("app/notas-debito/{$filename}");

            if (!file_exists($filepath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Archivo XML no encontrado',
                ], 404);
            }

            $xml = file_get_contents($filepath);

            return response($xml, 200)
                ->header('Content-Type', 'application/xml; charset=utf-8');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Convertir número a letras
     * TODO: Implementar librería completa de conversión (ej: luecano/numero-a-letras)
     */
    private function numeroALetras(float $numero, string $moneda = 'PEN'): string
    {
        $entero = floor($numero);
        $decimales = round(($numero - $entero) * 100);
        
        // Conversión básica temporal
        $unidades = ['', 'UNO', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE'];
        $decenas = ['', 'DIEZ', 'VEINTE', 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA'];
        $centenas = ['', 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS', 'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS'];
        
        $nombreMoneda = $moneda === 'USD' ? 'DÓLARES AMERICANOS' : 'SOLES';
        
        if ($entero == 0) {
            return "CERO CON $decimales/100 $nombreMoneda";
        }
        
        // Conversión simplificada para números hasta 999
        $texto = '';
        if ($entero >= 100) {
            $c = floor($entero / 100);
            $texto .= ($c == 1 && $entero > 100) ? 'CIENTO ' : $centenas[$c] . ' ';
            $entero %= 100;
        }
        if ($entero >= 10) {
            $d = floor($entero / 10);
            $texto .= $decenas[$d] . ' ';
            $entero %= 10;
        }
        if ($entero > 0) {
            $texto .= $unidades[$entero];
        }
        
        return "SON " . trim($texto) . " CON $decimales/100 $nombreMoneda";
    }
}
