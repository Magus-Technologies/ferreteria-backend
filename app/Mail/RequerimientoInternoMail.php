<?php

namespace App\Mail;

use App\Models\RequerimientoInterno;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RequerimientoInternoMail extends Mailable
{
    use Queueable, SerializesModels;

    public $requerimiento;
    public $pdfContent;
    public $fileName;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(RequerimientoInterno $requerimiento, string $pdfContent, string $fileName)
    {
        $this->requerimiento = $requerimiento;
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
        $nombreEmpresa = $this->requerimiento->user->empresa->razon_social ?? 'Nuestra Empresa';
        
        return $this->subject("Requerimiento Interno {$this->requerimiento->codigo} - {$nombreEmpresa}")
                    ->html("
                        <h2>Estimado,</h2>
                        <p>Adjunto a este correo encontrará el <strong>Requerimiento Interno {$this->requerimiento->codigo}</strong> generado por {$nombreEmpresa}.</p>
                        <p>Por favor revise el documento adjunto para conocer los detalles.</p>
                        <br>
                        <p>Saludos cordiales,</p>
                        <p><strong>{$nombreEmpresa}</strong></p>
                    ")
                    ->attachData($this->pdfContent, $this->fileName, [
                        'mime' => 'application/pdf',
                    ]);
    }
}
