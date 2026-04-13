<?php

namespace App\Http\Controllers;

use App\Mail\DocumentoPdfMail;
use App\Models\Empresa;
use App\Services\Pdf\CotizacionPdfService;
use App\Services\Pdf\GuiaPdfService;
use App\Services\Pdf\NotaCreditoPdfService;
use App\Services\Pdf\NotaDebitoPdfService;
use App\Services\Pdf\PrestamoPdfService;
use App\Services\Pdf\VentaPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class DocumentoEmailController extends Controller
{
    public function enviarEmail(Request $request): JsonResponse
    {
        $request->validate([
            'tipo' => 'required|string|in:venta,cotizacion,prestamo,guia,nota-credito,nota-debito',
            'id' => 'required|string',
            'email' => 'required|email',
            'formato' => 'nullable|string|in:ticket,a4',
        ]);

        $tipo = $request->input('tipo');
        $id = $request->input('id');
        $email = $request->input('email');
        $formato = $request->input('formato', 'a4');

        // Generar PDF usando los servicios existentes
        $pdfResponse = match ($tipo) {
            'venta' => app(VentaPdfService::class)->generar($id, $formato),
            'cotizacion' => app(CotizacionPdfService::class)->generar($id, $formato),
            'prestamo' => app(PrestamoPdfService::class)->generar($id, $formato),
            'guia' => app(GuiaPdfService::class)->generar($id, $formato),
            'nota-credito' => app(NotaCreditoPdfService::class)->generar($id),
            'nota-debito' => app(NotaDebitoPdfService::class)->generar($id),
        };

        $pdfContent = $pdfResponse->getContent();
        $fileName = "{$tipo}-{$id}.pdf";

        // Obtener nombre de empresa
        $empresa = Empresa::first();
        $empresaNombre = $empresa->razon_social ?? 'Nuestra Empresa';

        Mail::to($email)->send(new DocumentoPdfMail(
            tipoDocumento: $tipo,
            nombreDocumento: $id,
            pdfContent: $pdfContent,
            fileName: $fileName,
            empresaNombre: $empresaNombre,
        ));

        return response()->json([
            'message' => 'Documento enviado exitosamente por correo',
        ]);
    }
}
