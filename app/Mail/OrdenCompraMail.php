<?php

namespace App\Mail;

use App\Models\OrdenCompra;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrdenCompraMail extends Mailable
{
    use Queueable, SerializesModels;

    public $orden;
    public $pdfContent;
    public $fileName;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(OrdenCompra $orden, string $pdfContent, string $fileName)
    {
        $this->orden = $orden;
        $this->pdfContent = $pdfContent;
        $this->fileName = $fileName;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $nombreEmpresa = $this->orden->user->empresa->razon_social ?? 'Nuestra Empresa';
        
        return $this->subject("Orden de Compra {$this->orden->codigo} - {$nombreEmpresa}")
                    ->html("
                        <h2>Estimado Proveedor,</h2>
                        <p>Adjunto a este correo encontrará la <strong>Orden de Compra {$this->orden->codigo}</strong> generada por {$nombreEmpresa}.</p>
                        <p>Por favor revise el documento adjunto para conocer los detalles y condiciones.</p>
                        <br>
                        <p>Saludos cordiales,</p>
                        <p><strong>{$nombreEmpresa}</strong></p>
                    ")
                    ->attachData($this->pdfContent, $this->fileName, [
                        'mime' => 'application/pdf',
                    ]);
    }
}
