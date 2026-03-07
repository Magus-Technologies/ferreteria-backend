<?php

namespace App\Http\Controllers;

use App\Services\Pdf\CompraPdfService;
use App\Services\Pdf\CotizacionPdfService;
use App\Services\Pdf\PrestamoPdfService;
use App\Services\Pdf\VentaPdfService;
use Illuminate\Http\Response;

class PdfController extends Controller
{
    public function venta(string $id, VentaPdfService $service): Response
    {
        return $service->generar($id);
    }

    public function compra(string $id, CompraPdfService $service): Response
    {
        return $service->generar($id);
    }

    public function cotizacion(string $id, CotizacionPdfService $service): Response
    {
        return $service->generar($id);
    }

    public function prestamo(string $id, PrestamoPdfService $service): Response
    {
        return $service->generar($id);
    }
}
