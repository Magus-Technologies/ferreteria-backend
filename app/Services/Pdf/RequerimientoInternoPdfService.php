<?php

namespace App\Services\Pdf;

use App\Models\RequerimientoInterno;
use Illuminate\Http\Response;

class RequerimientoInternoPdfService
{
    public function generar(int $id, ?string $formato = 'a4', ?array $columnas = null): Response
    {
        $data = $this->prepararData($id, $formato, $columnas);
        $tipo = $data['requerimiento']->tipo_solicitud;
        $view = $this->getViewName($tipo, $formato);
        $filename = "{$data['requerimiento']->codigo}-LOG-F-03.pdf";

        return PdfService::render($view, $data, $filename, 'portrait', $this->paperSizeFor($formato));
    }

    public function generarBinario(int $id, ?string $formato = 'a4', ?array $columnas = null): string
    {
        $data = $this->prepararData($id, $formato, $columnas);
        $tipo = $data['requerimiento']->tipo_solicitud;
        $view = $this->getViewName($tipo, $formato);

        return PdfService::output($view, $data, 'portrait', $this->paperSizeFor($formato));
    }

    /**
     * Tamaño de papel según formato. Ticket = 80mm de ancho (226.77 pt),
     * alto suficiente para que dompdf no recorte el contenido.
     */
    private function paperSizeFor(?string $formato): ?array
    {
        return $formato === 'ticket' ? [0, 0, 226.77, 841.89] : null;
    }

    private function getViewName(string $tipo, string $formato): string
    {
        $tipoMap = ['OC' => 'compra', 'SOC' => 'compra', 'OS' => 'servicio'];
        $key = $tipoMap[$tipo] ?? 'compra';

        return "pdf.requerimiento-{$key}-{$formato}";
    }

    private function prepararData(int $id, string $formato, ?array $columnas = null): array
    {
        $requerimiento = $this->obtenerRequerimiento($id);
        $empresa = $requerimiento->user->empresa;

        $fecha = $requerimiento->created_at;
        $fechaFormato = $fecha
            ? \Carbon\Carbon::parse($fecha)->locale('es')->isoFormat('dddd, D [de] MMMM [de] YYYY')
            : '—';

        $productos = $this->prepararProductos($requerimiento);
        $servicios = $this->prepararServicios($requerimiento);

        return [
            'requerimiento' => $requerimiento,
            'empresa' => $empresa,
            'logoPath' => PdfService::getLogoPath($empresa->logo),
            'fechaFormato' => $fechaFormato,
            'productos' => $productos,
            'servicios' => $servicios,
            'columnas' => $columnas,
        ];
    }

    private function obtenerRequerimiento(int $id): RequerimientoInterno
    {
        return RequerimientoInterno::with([
            'user.empresa',
            'proveedorSugerido',
            'productos.producto.unidadMedida',
            'servicios',
            'vehiculo',
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

        if ($requerimiento->tipo_solicitud === 'SOC' && $requerimiento->productos->isNotEmpty()) {
            return $requerimiento->productos->map(function ($prod) {
                return [
                    'codigo' => $prod->producto?->cod_producto ?? '—',
                    'cantidad' => $prod->cantidad,
                    'unidad' => $prod->unidad ?? $prod->producto?->unidadMedida?->name ?? 'UND',
                    'descripcion' => $prod->producto?->name ?? $prod->nombre_adicional ?? '—',
                ];
            })->toArray();
        }

        return [];
    }

    private function prepararServicios(RequerimientoInterno $requerimiento): array
    {
        if ($requerimiento->tipo_solicitud === 'OS' && $requerimiento->servicios->isNotEmpty()) {
            return $requerimiento->servicios->map(function ($srv) {
                $horario = '';
                if ($srv->hora_inicio && $srv->hora_fin) {
                    $horario = "{$srv->hora_inicio} - {$srv->hora_fin}";
                } elseif ($srv->duracion_cantidad && $srv->duracion_unidad) {
                    $horario = "{$srv->duracion_cantidad} {$srv->duracion_unidad}";
                }

                $presupuesto = '—';
                if ($srv->presupuesto_referencial) {
                    $presupuesto = 'S/. ' . number_format((float) $srv->presupuesto_referencial, 2, '.', ',');
                }

                return [
                    'tipo' => $srv->tipo_servicio ?? '—',
                    'descripcion' => $srv->descripcion_servicio ?? '—',
                    'lugar' => $srv->lugar_ejecucion ?? '—',
                    'horario' => $horario,
                    'duracion' => $srv->duracion_cantidad
                        ? "{$srv->duracion_cantidad} {$srv->duracion_unidad}"
                        : '—',
                    'presupuesto' => $presupuesto,
                ];
            })->toArray();
        }

        return [];
    }
}