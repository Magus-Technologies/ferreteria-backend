<?php

namespace App\Services\Pdf;

use App\Models\RequerimientoInterno;
use Illuminate\Http\Response;

class RequerimientoInternoPdfService
{
    public function generar(int $id): Response
    {
        $requerimiento = $this->obtenerRequerimiento($id);
        $empresa = $requerimiento->user->empresa;

        $fecha = $requerimiento->created_at;
        $fechaFormato = $fecha
            ? \Carbon\Carbon::parse($fecha)->locale('es')->isoFormat('dddd, D [de] MMMM [de] YYYY')
            : '—';

        $productos = $this->prepararProductos($requerimiento);

        $data = [
            'requerimiento' => $requerimiento,
            'empresa' => $empresa,
            'logoPath' => PdfService::getLogoPath($empresa->logo),
            'fechaFormato' => $fechaFormato,
            'productos' => $productos,
        ];

        return PdfService::render(
            'pdf.requerimiento-interno',
            $data,
            "{$requerimiento->codigo}-LOG-F-03.pdf",
        );
    }

    private function obtenerRequerimiento(int $id): RequerimientoInterno
    {
        return RequerimientoInterno::with([
            'user.empresa',
            'proveedorSugerido',
            'productos.producto.unidadMedida',
            'servicios',
        ])->findOrFail($id);
    }

    private function prepararProductos(RequerimientoInterno $requerimiento): array
    {
        if ($requerimiento->tipo_solicitud === 'OC' && $requerimiento->productos->isNotEmpty()) {
            return $requerimiento->productos->map(function ($prod) {
                return [
                    'codigo' => $prod->producto?->cod_producto ?? '—',
                    'cantidad' => $prod->cantidad,
                    'unidad' => $prod->unidad ?? $prod->producto?->unidadMedida?->name ?? 'UND',
                    'descripcion' => $prod->producto?->name ?? $prod->nombre_adicional ?? '—',
                ];
            })->toArray();
        }

        if ($requerimiento->tipo_solicitud === 'OS' && $requerimiento->servicios->isNotEmpty()) {
            return $requerimiento->servicios->map(function ($srv) {
                return [
                    'codigo' => '—',
                    'cantidad' => 1,
                    'unidad' => 'SRV',
                    'descripcion' => ($srv->tipo_servicio ? $srv->tipo_servicio . ': ' : '') . $srv->descripcion_servicio . ($srv->detalles ? " (" . $srv->detalles . ")" : ""),
                ];
            })->toArray();
        }

        return [];
    }
}
