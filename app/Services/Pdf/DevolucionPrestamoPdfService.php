<?php

namespace App\Services\Pdf;

use App\Models\PrestamoDevolucion;
use App\Services\Pdf\Traits\ResuelveEstilosPlantilla;
use Illuminate\Http\Response;

class DevolucionPrestamoPdfService
{
    use ResuelveEstilosPlantilla;

    public function generar(string $prestamoId, string $numeroDevolucion, string $formato = 'a4'): Response
    {
        $devolucion = $this->obtenerDevolucion($prestamoId, $numeroDevolucion);
        $prestamo = $devolucion->prestamo;
        $empresa = $prestamo->user->empresa;

        $productos = $this->prepararProductos($devolucion);

        $esCliente = $prestamo->tipo_entidad === 'CLIENTE';
        $entidad = $esCliente ? $prestamo->cliente : $prestamo->proveedor;
        $entidadNombre = $entidad?->razon_social
            ?: trim(($entidad?->nombres ?? '') . ' ' . ($entidad?->apellidos ?? ''))
            ?: 'ENTIDAD GENERAL';
        $entidadDocumento = $prestamo->ruc_dni ?: ($entidad?->numero_documento ?? '');

        $tipoOperacion = $prestamo->tipo_operacion === 'PRESTAR'
            ? 'DEVOLUCION DE PRESTAMO'
            : 'DEVOLUCION DE PRESTAMO RECIBIDO';

        $usuarioRegistro = $devolucion->user->name ?? '-';
        $observaciones = $devolucion->observaciones ?: '- NO HAY OBSERVACIONES ADICIONALES';

        if ($formato === 'ticket') {
            return $this->generarTicket(
                $devolucion,
                $prestamo,
                $empresa,
                $productos,
                $tipoOperacion,
                $esCliente,
                $entidadNombre,
                $entidadDocumento,
                $usuarioRegistro,
                $observaciones,
            );
        }

        $estilos = $this->prepararDatosPlantilla((int) $empresa->id, 'prestamo', 'A4');

        $data = array_merge($estilos, [
            'devolucion' => $devolucion,
            'prestamo' => $prestamo,
            'empresa' => $empresa,
            'logoPath' => PdfService::getLogoPath($empresa->logo),
            'tipoDocumentoTitulo' => $tipoOperacion,
            'numeroDocumento' => $devolucion->numero_devolucion,
            'productos' => $productos,
            'filas' => $this->prepararInfoEntidad($devolucion, $prestamo, $esCliente, $entidadNombre, $entidadDocumento, $usuarioRegistro),
            'observaciones' => $observaciones,
            'esCliente' => $esCliente,
            'entidadNombre' => $entidadNombre,
            'entidadDocumento' => $entidadDocumento,
        ]);

        $filename = "DEVOLUCION-PRESTAMO-{$prestamo->numero}-{$devolucion->numero_devolucion}.pdf";

        return PdfService::render('pdf.devolucion-prestamo', $data, $filename);
    }

    private function generarTicket(
        PrestamoDevolucion $devolucion,
        $prestamo,
        $empresa,
        array $productos,
        string $tipoOperacion,
        bool $esCliente,
        string $entidadNombre,
        string $entidadDocumento,
        string $usuarioRegistro,
        string $observaciones,
    ): Response {
        $estilos = $this->prepararDatosPlantilla((int) $empresa->id, 'prestamo', 'Ticket');

        $data = array_merge($estilos, [
            'titulo' => "Devolucion {$devolucion->numero_devolucion}",
            'empresa' => $empresa,
            'logoPath' => PdfService::getLogoPath($empresa->logo),
            'tipoOperacion' => $tipoOperacion,
            'numeroDocumento' => $devolucion->numero_devolucion,
            'numeroPrestamo' => $prestamo->numero,
            'fechaDevolucion' => PdfService::formatFecha($devolucion->fecha_devolucion, 'd/m/Y H:i'),
            'esCliente' => $esCliente,
            'entidadNombre' => $entidadNombre,
            'entidadDocumento' => $entidadDocumento,
            'productos' => $productos,
            'usuarioRegistro' => $usuarioRegistro,
            'observaciones' => $observaciones,
        ]);

        $filename = "TICKET-DEVOLUCION-PRESTAMO-{$prestamo->numero}-{$devolucion->numero_devolucion}.pdf";

        return PdfService::render(
            'pdf.devolucion-prestamo-ticket',
            $data,
            $filename,
            'portrait',
            [0, 0, 226.77, 841.89],
        );
    }

    private function obtenerDevolucion(string $prestamoId, string $numeroDevolucion): PrestamoDevolucion
    {
        $devolucion = PrestamoDevolucion::with([
            'productosDevueltos.productoAlmacenPrestamo.productoAlmacen.producto.marca',
            'productosDevueltos.productoAlmacenPrestamo.unidadesDerivadas',
            'user',
            'prestamo.user.empresa',
            'prestamo.cliente',
            'prestamo.proveedor',
            'prestamo.almacen',
        ])
            ->where('prestamo_id', $prestamoId)
            ->where('numero_devolucion', $numeroDevolucion)
            ->first();

        if (!$devolucion) {
            abort(404, 'Devolución no encontrada');
        }

        return $devolucion;
    }

    private function prepararProductos(PrestamoDevolucion $devolucion): array
    {
        $productos = [];

        foreach ($devolucion->productosDevueltos as $pd) {
            $pap = $pd->productoAlmacenPrestamo;
            $producto = $pap?->productoAlmacen?->producto;
            $unidad = $pap?->unidadesDerivadas?->first();

            $productos[] = [
                'codigo' => $producto->cod_producto ?? '',
                'nombre' => $producto->name ?? 'N/A',
                'unidad' => $unidad->name ?? '',
                'cantidad' => (float) $pd->cantidad,
            ];
        }

        return $productos;
    }

    private function prepararInfoEntidad(
        PrestamoDevolucion $devolucion,
        $prestamo,
        bool $esCliente,
        string $entidadNombre,
        string $entidadDocumento,
        string $usuarioRegistro,
    ): array {
        return [
            [
                'N° Devolución' => $devolucion->numero_devolucion,
                'F. Devolución' => PdfService::formatFecha($devolucion->fecha_devolucion, 'd/m/Y H:i'),
            ],
            [
                'N° Préstamo' => $prestamo->numero,
                'Registrado por' => $usuarioRegistro,
            ],
            [
                ($esCliente ? 'Cliente' : 'Proveedor') => $entidadNombre,
                'RUC / DNI' => $entidadDocumento,
            ],
        ];
    }
}
