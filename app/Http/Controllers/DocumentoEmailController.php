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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class DocumentoEmailController extends Controller
{
    public function enviarEmail(Request $request): JsonResponse
    {
        $request->validate([
            'tipo' => 'required|string|in:venta,cotizacion,prestamo,guia,nota-credito,nota-debito',
            'id' => 'required|string',
            'email' => 'required|email',
            'formato' => 'nullable|string|in:ticket,a4',
            'mensaje' => 'nullable|string|max:1000',
            'columnas' => 'nullable|array',
        ]);

        $tipo = $request->input('tipo');
        $id = $request->input('id');
        $email = $request->input('email');
        $formato = $request->input('formato', 'a4');
        $mensaje = $request->input('mensaje');
        $columnas = $request->input('columnas');
        $columnas = is_array($columnas) ? $columnas : null;

        // Generar PDF usando los servicios existentes
        $pdfResponse = match ($tipo) {
            'venta' => app(VentaPdfService::class)->generar($id, $formato),
            'cotizacion' => app(CotizacionPdfService::class)->generar($id, $formato),
            'prestamo' => app(PrestamoPdfService::class)->generar($id, $formato, $columnas),
            'guia' => app(GuiaPdfService::class)->generar($id, $formato),
            'nota-credito' => app(NotaCreditoPdfService::class)->generar($id),
            'nota-debito' => app(NotaDebitoPdfService::class)->generar($id),
        };

        $pdfContent = $pdfResponse->getContent();
        $fileName = "{$tipo}-{$id}.pdf";

        // Obtener nombre de empresa
        $empresa = Empresa::first();
        $empresaNombre = $empresa->razon_social ?? 'Nuestra Empresa';

        try {
            Mail::to($email)->send(new DocumentoPdfMail(
                tipoDocumento: $tipo,
                nombreDocumento: $id,
                pdfContent: $pdfContent,
                fileName: $fileName,
                empresaNombre: $empresaNombre,
                mensajePersonalizado: $mensaje,
            ));
        } catch (TransportExceptionInterface $e) {
            // No exponer detalles del transporte SMTP (host, puerto, stack
            // trace) al usuario final — solo queda en el log del servidor
            // para diagnóstico.
            Log::error('Error al enviar documento por correo', [
                'tipo' => $tipo,
                'id' => $id,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'No se pudo enviar el correo. El servicio de correo no está disponible en este momento — intenta de nuevo más tarde o contacta a soporte.',
            ], 502);
        }

        return response()->json([
            'message' => 'Documento enviado exitosamente por correo',
        ]);
    }
}
