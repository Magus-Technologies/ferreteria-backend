<?php

namespace App\Services;

use App\Models\AperturaCierreCaja;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class TicketAperturaService
{
    /**
     * Generar HTML del ticket de apertura para envío por correo
     */
    public function generarTicketHTML(AperturaCierreCaja $apertura): string
    {
        $fechaApertura = $apertura->fecha_apertura ? 
            Carbon::parse($apertura->fecha_apertura)->format('d/m/Y h:i:s a') : 'N/A';

        // Cargar distribuciones de vendedores
        $apertura->load(['distribucionesVendedores.vendedor', 'cajaPrincipal', 'user']);

        $distribuciones = $apertura->distribucionesVendedores->map(function ($dist) {
            return [
                'vendedor' => $dist->vendedor->name,
                'monto' => $dist->monto,
                'conteo_billetes_monedas' => $dist->conteo_billetes_monedas,
            ];
        });

        $cantidadVendedores = $distribuciones->count();

        return view('emails.ticket-apertura-simple', compact(
            'apertura',
            'fechaApertura',
            'distribuciones',
            'cantidadVendedores'
        ))->render();
    }

    /**
     * Generar PDF del ticket de apertura
     */
    public function generarTicketPDF(AperturaCierreCaja $apertura): \Barryvdh\DomPDF\PDF
    {
        $fechaApertura = $apertura->fecha_apertura ? 
            Carbon::parse($apertura->fecha_apertura)->format('d/m/Y h:i:s a') : 'N/A';

        // Cargar distribuciones de vendedores
        $apertura->load(['distribucionesVendedores.vendedor', 'cajaPrincipal', 'user']);

        $distribuciones = $apertura->distribucionesVendedores->map(function ($dist) {
            return [
                'vendedor' => $dist->vendedor->name,
                'monto' => $dist->monto,
                'conteo_billetes_monedas' => $dist->conteo_billetes_monedas,
            ];
        });

        $cantidadVendedores = $distribuciones->count();

        // Generar PDF usando la misma vista
        $pdf = Pdf::loadView('emails.ticket-apertura-simple', compact(
            'apertura',
            'fechaApertura',
            'distribuciones',
            'cantidadVendedores'
        ));

        // Configurar el PDF
        $pdf->setPaper('a4', 'portrait');

        return $pdf;
    }
}
