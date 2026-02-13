<?php

namespace App\Services;

use App\Models\AperturaCierreCaja;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class TicketAperturaService
{
    /**
     * Generar HTML del ticket de apertura para envío por correo
     * 
     * @param AperturaCierreCaja $apertura
     * @param string|null $userId Si se proporciona, solo muestra la distribución de ese usuario
     */
    public function generarTicketHTML(AperturaCierreCaja $apertura, ?string $userId = null): string
    {
        $fechaApertura = $apertura->fecha_apertura ? 
            Carbon::parse($apertura->fecha_apertura)->format('d/m/Y h:i:s a') : 'N/A';

        // Cargar distribuciones de vendedores
        $apertura->load(['distribucionesVendedores.vendedor', 'cajaPrincipal', 'user']);

        // Filtrar distribuciones si se proporciona un userId
        $distribuciones = $apertura->distribucionesVendedores;
        if ($userId) {
            $distribuciones = $distribuciones->filter(function ($dist) use ($userId) {
                return $dist->user_id === $userId;
            });
        }

        // Consolidar distribuciones por usuario (sumar montos de mismo vendedor)
        $distribucionesConsolidadas = [];
        foreach ($distribuciones as $dist) {
            $userIdKey = $dist->user_id;
            if (!isset($distribucionesConsolidadas[$userIdKey])) {
                $distribucionesConsolidadas[$userIdKey] = [
                    'vendedor' => $dist->vendedor->name,
                    'monto' => 0,
                    'conteo_billetes_monedas' => $dist->conteo_billetes_monedas, // Usar el último conteo
                ];
            }
            $distribucionesConsolidadas[$userIdKey]['monto'] += $dist->monto;
            // Actualizar conteo si existe en esta distribución
            if ($dist->conteo_billetes_monedas) {
                $distribucionesConsolidadas[$userIdKey]['conteo_billetes_monedas'] = $dist->conteo_billetes_monedas;
            }
        }

        $distribuciones = collect(array_values($distribucionesConsolidadas));
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
     * 
     * @param AperturaCierreCaja $apertura
     * @param string|null $userId Si se proporciona, solo muestra la distribución de ese usuario
     */
    public function generarTicketPDF(AperturaCierreCaja $apertura, ?string $userId = null): \Barryvdh\DomPDF\PDF
    {
        $fechaApertura = $apertura->fecha_apertura ? 
            Carbon::parse($apertura->fecha_apertura)->format('d/m/Y h:i:s a') : 'N/A';

        // Cargar distribuciones de vendedores
        $apertura->load(['distribucionesVendedores.vendedor', 'cajaPrincipal', 'user']);

        // Filtrar distribuciones si se proporciona un userId
        $distribuciones = $apertura->distribucionesVendedores;
        if ($userId) {
            $distribuciones = $distribuciones->filter(function ($dist) use ($userId) {
                return $dist->user_id === $userId;
            });
        }

        // Consolidar distribuciones por usuario (sumar montos de mismo vendedor)
        $distribucionesConsolidadas = [];
        foreach ($distribuciones as $dist) {
            $userIdKey = $dist->user_id;
            if (!isset($distribucionesConsolidadas[$userIdKey])) {
                $distribucionesConsolidadas[$userIdKey] = [
                    'vendedor' => $dist->vendedor->name,
                    'monto' => 0,
                    'conteo_billetes_monedas' => $dist->conteo_billetes_monedas, // Usar el último conteo
                ];
            }
            $distribucionesConsolidadas[$userIdKey]['monto'] += $dist->monto;
            // Actualizar conteo si existe en esta distribución
            if ($dist->conteo_billetes_monedas) {
                $distribucionesConsolidadas[$userIdKey]['conteo_billetes_monedas'] = $dist->conteo_billetes_monedas;
            }
        }

        $distribuciones = collect(array_values($distribucionesConsolidadas));
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

    /**
     * Generar HTML del ticket con distribuciones específicas (no carga desde BD)
     * 
     * @param AperturaCierreCaja $apertura
     * @param \Illuminate\Support\Collection $distribuciones Distribuciones ya preparadas
     * @param float $montoOperacion Monto de esta operación específica
     */
    public function generarTicketHTMLConDistribuciones(
        AperturaCierreCaja $apertura, 
        \Illuminate\Support\Collection $distribuciones,
        float $montoOperacion
    ): string
    {
        $fechaApertura = $apertura->fecha_apertura ? 
            Carbon::parse($apertura->fecha_apertura)->format('d/m/Y h:i:s a') : 'N/A';

        // Cargar solo las relaciones necesarias (no distribuciones)
        $apertura->load(['cajaPrincipal', 'user']);

        $cantidadVendedores = $distribuciones->count();

        // Crear una copia temporal del apertura con el monto de esta operación
        $aperturaTemp = clone $apertura;
        $aperturaTemp->monto_apertura = $montoOperacion;

        return view('emails.ticket-apertura-simple', [
            'apertura' => $aperturaTemp,
            'fechaApertura' => $fechaApertura,
            'distribuciones' => $distribuciones,
            'cantidadVendedores' => $cantidadVendedores,
        ])->render();
    }

    /**
     * Generar PDF del ticket con distribuciones específicas (no carga desde BD)
     * 
     * @param AperturaCierreCaja $apertura
     * @param \Illuminate\Support\Collection $distribuciones Distribuciones ya preparadas
     * @param float $montoOperacion Monto de esta operación específica
     */
    public function generarTicketPDFConDistribuciones(
        AperturaCierreCaja $apertura, 
        \Illuminate\Support\Collection $distribuciones,
        float $montoOperacion
    ): \Barryvdh\DomPDF\PDF
    {
        $fechaApertura = $apertura->fecha_apertura ? 
            Carbon::parse($apertura->fecha_apertura)->format('d/m/Y h:i:s a') : 'N/A';

        // Cargar solo las relaciones necesarias (no distribuciones)
        $apertura->load(['cajaPrincipal', 'user']);

        $cantidadVendedores = $distribuciones->count();

        // Crear una copia temporal del apertura con el monto de esta operación
        $aperturaTemp = clone $apertura;
        $aperturaTemp->monto_apertura = $montoOperacion;

        // Generar PDF usando la misma vista
        $pdf = Pdf::loadView('emails.ticket-apertura-simple', [
            'apertura' => $aperturaTemp,
            'fechaApertura' => $fechaApertura,
            'distribuciones' => $distribuciones,
            'cantidadVendedores' => $cantidadVendedores,
        ]);

        // Configurar el PDF
        $pdf->setPaper('a4', 'portrait');

        return $pdf;
    }
}
