<?php

namespace App\Services;

use App\Models\AperturaCierreCaja;
use Carbon\Carbon;

class TicketCierreService
{
    /**
     * Generar HTML del ticket para envío por correo
     * El HTML se genera en el backend porque el correo se envía desde el servidor
     */
    public function generarTicketHTML(AperturaCierreCaja $cierre): string
    {
        $apertura = $cierre->fecha_apertura ? 
            Carbon::parse($cierre->fecha_apertura)->format('d/m/Y h:i:s a') : 'N/A';
        $cierreDate = $cierre->fecha_cierre ? 
            Carbon::parse($cierre->fecha_cierre)->format('d/m/Y h:i:s a') : 'N/A';

        $diferencia = ($cierre->saldo_actual ?? 0) - ($cierre->total_cierre_fisico ?? 0);
        $estadoDiferencia = '';
        $colorDiferencia = '';
        
        if ($diferencia > 0) {
            $estadoDiferencia = 'Faltante: S/ ' . number_format(abs($diferencia), 2);
            $colorDiferencia = '#dc2626';
        } elseif ($diferencia < 0) {
            $estadoDiferencia = 'Sobrante: S/ ' . number_format(abs($diferencia), 2);
            $colorDiferencia = '#16a34a';
        } else {
            $estadoDiferencia = 'Sin diferencias';
            $colorDiferencia = '#16a34a';
        }

        return view('emails.ticket-cierre', compact(
            'cierre',
            'apertura',
            'cierreDate',
            'diferencia',
            'estadoDiferencia',
            'colorDiferencia'
        ))->render();
    }
}
