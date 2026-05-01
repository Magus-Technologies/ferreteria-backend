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
use App\Services\Pdf\CobroVentaPdfService;
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
        return $service->generar($id, $formato);
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

    public function notaCredito(string $id, NotaCreditoPdfService $service): Response
    {
        return $service->generar($id);
    }

    public function notaDebito(string $id, NotaDebitoPdfService $service): Response
    {
        return $service->generar($id);
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
        return $service->generar($id, is_array($columnas) ? $columnas : null);
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

    public function requerimientoInterno(int $id, RequerimientoInternoPdfService $service): Response
    {
        return $service->generar($id);
    }

    public function entregaProducto(int $id, Request $request, EntregaProductoPdfService $service): Response
    {
        // Formato por defecto = ticket (80mm térmico) — es el formato más usado.
        // Si el usuario pide ?formato=a4, se renderiza la versión carta.
        $formato = $request->query('formato', 'ticket');
        return $service->generar($id, $formato);
    }

    public function cobroVenta(string $id, CobroVentaPdfService $service): Response
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
}
