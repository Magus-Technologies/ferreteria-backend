<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DocumentoPdfMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $tipoDocumento;
    public string $nombreDocumento;
    public string $pdfContent;
    public string $fileName;
    public string $empresaNombre;
    public ?string $mensajePersonalizado;

    public function __construct(
        string $tipoDocumento,
        string $nombreDocumento,
        string $pdfContent,
        string $fileName,
        string $empresaNombre,
        ?string $mensajePersonalizado = null
    ) {
        $this->tipoDocumento = $tipoDocumento;
        $this->nombreDocumento = $nombreDocumento;
        $this->pdfContent = $pdfContent;
        $this->fileName = $fileName;
        $this->empresaNombre = $empresaNombre;
        $this->mensajePersonalizado = $mensajePersonalizado;
    }

    public function build()
    {
        $tipo = match ($this->tipoDocumento) {
            'venta' => 'Comprobante de Venta',
            'cotizacion' => 'Cotización',
            'prestamo' => 'Préstamo',
            'guia' => 'Guía de Remisión',
            'nota-credito' => 'Nota de Crédito',
            'nota-debito' => 'Nota de Débito',
            default => 'Documento',
        };

        // Usar mensaje personalizado o el mensaje por defecto
        if ($this->mensajePersonalizado) {
            $mensajeHtml = nl2br(e($this->mensajePersonalizado));
            $body = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <p>{$mensajeHtml}</p>
                    <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
                    <p style='color: #666; font-size: 12px;'>Documento adjunto: <strong>{$tipo} {$this->nombreDocumento}</strong></p>
                    <p style='color: #666; font-size: 12px;'><strong>{$this->empresaNombre}</strong></p>
                </div>
            ";
        } else {
            $body = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <h2>Estimado Cliente,</h2>
                    <p>Adjunto a este correo encontrará el documento <strong>{$tipo} {$this->nombreDocumento}</strong> emitido por {$this->empresaNombre}.</p>
                    <p>Por favor revise el documento adjunto.</p>
                    <br>
                    <p>Saludos cordiales,</p>
                    <p><strong>{$this->empresaNombre}</strong></p>
                </div>
            ";
        }

        return $this->subject("{$tipo} {$this->nombreDocumento} - {$this->empresaNombre}")
                    ->html($body)
                    ->attachData($this->pdfContent, $this->fileName, [
                        'mime' => 'application/pdf',
                    ]);
    }
}
