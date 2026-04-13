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

    public function __construct(
        string $tipoDocumento,
        string $nombreDocumento,
        string $pdfContent,
        string $fileName,
        string $empresaNombre
    ) {
        $this->tipoDocumento = $tipoDocumento;
        $this->nombreDocumento = $nombreDocumento;
        $this->pdfContent = $pdfContent;
        $this->fileName = $fileName;
        $this->empresaNombre = $empresaNombre;
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

        return $this->subject("{$tipo} {$this->nombreDocumento} - {$this->empresaNombre}")
                    ->html("
                        <h2>Estimado Cliente,</h2>
                        <p>Adjunto a este correo encontrará el documento <strong>{$tipo} {$this->nombreDocumento}</strong> emitido por {$this->empresaNombre}.</p>
                        <p>Por favor revise el documento adjunto.</p>
                        <br>
                        <p>Saludos cordiales,</p>
                        <p><strong>{$this->empresaNombre}</strong></p>
                    ")
                    ->attachData($this->pdfContent, $this->fileName, [
                        'mime' => 'application/pdf',
                    ]);
    }
}
