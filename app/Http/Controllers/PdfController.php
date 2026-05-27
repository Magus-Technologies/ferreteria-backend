<?php

namespace App\Http\Controllers;

use App\Services\Pdf\AperturaCajaPdfService;
use App\Services\Pdf\CierreCajaPdfService;
use App\Services\Pdf\CompraPdfService;
use App\Services\Pdf\CotizacionPdfService;
use App\Services\Pdf\GuiaPdfService;
use App\Services\Pdf\IngresoSalidaPdfService;
use App\Services\Pdf\NotaCreditoPdfService;
use App\Services\Pdf\NotaDebitoPdfService;
use App\Services\Pdf\OrdenCompraPdfService;
use App\Services\Pdf\PrestamoPdfService;
use App\Services\Pdf\RecepcionAlmacenPdfService;
use App\Services\Pdf\RequerimientoInternoPdfService;
use App\Services\Pdf\ValeCompraPdfService;
use App\Services\Pdf\EntregaProductoPdfService;
use App\Services\Pdf\EntregaNuevaPdfService;
use App\Services\Pdf\CobroVentaPdfService;
use App\Services\Pdf\PagoCompraPdfService;
use App\Services\Pdf\VentaPdfService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PdfController extends Controller
{
    public function venta(string $id, Request $request, VentaPdfService $service): Response
    {
        $formato = $request->query('formato', 'a4');
        $sinVales = $request->boolean('sin_vales', false);
        return $service->generar($id, $formato, $sinVales);
    }

    public function ventaValeGenerado(string $id, int $index, VentaPdfService $service): Response
    {
        return $service->generarValeIndividual($id, $index);
    }

    public function compra(string $id, CompraPdfService $service): Response
    {
        return $service->generar($id);
    }

    public function cotizacion(string $id, Request $request, CotizacionPdfService $service): Response
    {
        $formato = $request->query('formato', 'a4');
        return $service->generar($id, $formato);
    }

    public function prestamo(string $id, Request $request, PrestamoPdfService $service): Response
    {
        $formato = $request->query('formato', 'a4');
        $columnas = $request->input('columnas');
        return $service->generar($id, $formato, is_array($columnas) ? $columnas : null);
    }

    public function guia(string $id, Request $request, GuiaPdfService $service): Response
    {
        $formato = $request->query('formato', 'a4');
        return $service->generar($id, $formato);
    }

    public function vale(int $id, ValeCompraPdfService $service): Response
    {
        return $service->generar($id);
    }

    public function notaCredito(string $id, Request $request, NotaCreditoPdfService $service): Response
    {
        $formato = $request->query('formato', 'a4');
        return $service->generar($id, $formato);
    }

    public function notaDebito(string $id, Request $request, NotaDebitoPdfService $service): Response
    {
        $formato = $request->query('formato', 'a4');
        return $service->generar($id, $formato);
    }

    public function cierreCaja(string $id, Request $request, CierreCajaPdfService $service): Response
    {
        $formato = $request->query('formato', 'ticket');
        return $service->generar($id, $formato);
    }

    public function aperturaCaja(string $id, Request $request, AperturaCajaPdfService $service): Response
    {
        $formato = $request->query('formato', 'ticket');
        return $service->generar($id, $formato);
    }

    public function ordenCompra(int $id, Request $request, OrdenCompraPdfService $service): Response
    {
        $columnas = $request->input('columnas');
        $formato = $request->query('formato', 'a4');
        return $service->generar($id, is_array($columnas) ? $columnas : null, $formato);
    }

    public function ingresoSalida(int $id, Request $request, IngresoSalidaPdfService $service): Response
    {
        $formato = $request->query('formato', 'a4');
        return $service->generar($id, $formato);
    }

    public function transferenciaStock(int $id, Request $request, \App\Services\Pdf\TransferenciaStockPdfService $service): Response
    {
        $formato = $request->query('formato', 'ticket');
        return $service->generar($id, $formato);
    }

    public function recepcionAlmacen(int $id, Request $request, RecepcionAlmacenPdfService $service): Response
    {
        $formato = $request->query('formato', 'a4');
        return $service->generar($id, $formato);
    }

    public function requerimientoInterno(int $id, Request $request, RequerimientoInternoPdfService $service): Response
    {
        $formato = $request->query('formato', 'a4');
        return $service->generar($id, $formato);
    }

    public function entregaProducto(int $id, Request $request, EntregaProductoPdfService $service): Response
    {
        // Formato por defecto = ticket (80mm térmico) — es el formato más usado.
        // Si el usuario pide ?formato=a4, se renderiza la versión carta.
        $formato = $request->query('formato', 'ticket');
        return $service->generar($id, $formato);
    }

    public function entregaNueva(int $id, Request $request, EntregaNuevaPdfService $service): Response
    {
        $formato = $request->query('formato', 'ticket');
        return $service->generar($id, $formato);
    }

    public function cobroVenta(string $id, CobroVentaPdfService $service): Response
    {
        return $service->generar($id);
    }

    public function pagoCompra(string $id, PagoCompraPdfService $service): Response
    {
        return $service->generar($id);
    }

    public function cobroVentaMultiple(Request $request, CobroVentaPdfService $service): Response
    {
        $ids = $request->input('ids');
        if (!is_array($ids)) {
            $ids = explode(',', (string) $ids);
        }
        return $service->generarMasivo($ids);
    }

    public function reporteVentasPorCobrar(Request $request): Response
    {
        $request->validate([
            'venta_ids' => 'required|array',
        ]);

        $ventaIds = $request->input('venta_ids');

        // Obtener todos los cobros de las ventas filtradas
        $cobros = \App\Models\CobroVenta::whereIn('venta_id', $ventaIds)
            ->where('estado', '!=', 0) // Solo cobros activos
            ->with(['venta.cliente', 'despliegueDePago.metodoDePago', 'user'])
            ->orderBy('venta_id')
            ->orderBy('fecha')
            ->get();

        if ($cobros->isEmpty()) {
            return response()->json(['message' => 'No hay cobros registrados para las ventas seleccionadas'], 404);
        }

        // Obtener empresa y logo
        $empresa = \App\Models\Empresa::first();
        $logoPath = null;
        if ($empresa && $empresa->logo) {
            $logoPath = storage_path('app/public/' . $empresa->logo);
            if (!file_exists($logoPath)) {
                $logoPath = null;
            }
        }

        // Preparar datos para cada cobro (igual que el ticket masivo)
        $cobrosData = [];
        foreach ($cobros as $cobro) {
            $venta = $cobro->venta;
            
            // Calcular total de la venta
            $totalNeto = 0;
            foreach ($venta->productos_por_almacen ?? [] as $item) {
                foreach ($item->unidades_derivadas ?? [] as $u) {
                    $precio = floatval($u->precio ?? 0);
                    $cantidad = floatval($u->cantidad ?? 0);
                    $descuento = floatval($u->descuento ?? 0);
                    $bonificacion = boolval($u->bonificacion ?? false);
                    $montoLinea = $bonificacion ? 0 : ($precio * $cantidad) - $descuento;
                    $totalNeto += $montoLinea;
                }
            }

            // Calcular total cobrado hasta este cobro
            $totalCobrado = \App\Models\CobroVenta::where('venta_id', $venta->id)
                ->where('estado', '!=', 0)
                ->sum('monto');

            $saldoPendiente = $totalNeto - $totalCobrado;

            // Tipo de documento
            $tipoDocMap = ['01' => 'FACTURA', '03' => 'BOLETA', 'nv' => 'NOTA DE VENTA'];
            $tipoDocValue = $venta->tipo_documento instanceof \App\Enums\TipoDocumento 
                ? $venta->tipo_documento->value 
                : $venta->tipo_documento;
            $tipoDoc = $tipoDocMap[$tipoDocValue ?? ''] ?? $tipoDocValue ?? '';
            $nroDocumento = "{$venta->serie}-{$venta->numero}";

            // Cliente
            $clienteNombre = $venta->cliente->razon_social ?? 
                trim(($venta->cliente->nombres ?? '') . ' ' . ($venta->cliente->apellidos ?? '')) ?: 'Sin cliente';
            $clienteDoc = $venta->cliente->numero_documento ?? '-';

            // Método de pago
            $metodoPago = $cobro->despliegueDePago->metodoDePago->name ?? 'Efectivo';
            if ($cobro->despliegueDePago) {
                $metodoPago .= ' / ' . ($cobro->despliegueDePago->name ?? '');
            }

            // Usuario que registró
            $registradoPor = $cobro->user->name ?? 'Sistema';

            $cobrosData[] = [
                'cobro' => $cobro,
                'empresa' => $empresa,
                'metodoPago' => $metodoPago,
                'registradoPor' => $registradoPor,
                'tipoDoc' => $tipoDoc,
                'nroDocumento' => $nroDocumento,
                'clienteNombre' => $clienteNombre,
                'clienteDoc' => $clienteDoc,
                'totalNeto' => number_format($totalNeto, 2),
                'totalCobrado' => number_format($totalCobrado, 2),
                'saldoPendiente' => number_format($saldoPendiente, 2),
            ];
        }

        // Usar el mismo blade que los tickets masivos
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.cobro-venta-ticket-masivo', [
            'cobros' => $cobrosData,
            'logoPath' => $logoPath,
        ]);

        // Configurar papel continuo (80mm de ancho, altura automática según contenido)
        $pdf->setPaper([0, 0, 226.77, 99999], 'portrait'); // 80mm width, altura muy grande para evitar saltos

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="tickets-cobros-ventas-filtradas.pdf"',
        ]);
    }
}
