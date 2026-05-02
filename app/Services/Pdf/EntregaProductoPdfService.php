<?php

namespace App\Services\Pdf;

use App\Models\EntregaProducto;
use Illuminate\Http\Response;

class EntregaProductoPdfService
{
    public function generar(int $id, string $formato = 'ticket'): Response
    {
        $entrega = EntregaProducto::with([
            'venta.cliente',
            // Historial de ediciones de la venta — usado para mostrar
            // "productos anteriores vs actuales" cuando la venta fue editada.
            'venta.historial' => fn ($q) => $q->where('accion', 'edicion')->orderBy('fecha', 'desc'),
            'almacenSalida',
            'despachador',
            'vehiculo',
            'user',
            'productosEntregados.unidadDerivadaVenta.productoAlmacenVenta.productoAlmacen.producto',
            'productosEntregados.unidadDerivadaVenta.unidadDerivadaInmutable',
        ])->findOrFail($id);

        $empresa = $entrega->user->empresa ?? $entrega->venta->user->empresa ?? null;
        if (!$empresa) {
            // Try to get empresa from user relation
            $entrega->load('user.empresa', 'venta.user.empresa');
            $empresa = $entrega->user->empresa ?? $entrega->venta->user->empresa;
        }

        $cliente = $entrega->venta->cliente ?? null;
        $productos = $this->prepararProductos($entrega);

        // Format venta number
        $serie = $entrega->venta->serie ?? '';
        $numero = $entrega->venta->numero ?? '';
        $nroVenta = $serie && $numero ? "{$serie}-{$numero}" : ($entrega->venta->codigo ?? '—');

        // Tipo entrega label
        $tipoEntregaLabel = match($entrega->tipo_entrega) {
            'rt' => 'Recojo en Tienda',
            'de' => 'Despacho a Domicilio',
            'pa' => 'Parcial',
            default => $entrega->tipo_entrega,
        };

        // Tipo despacho label
        $tipoDespachoLabel = match($entrega->tipo_despacho) {
            'in' => 'Inmediato',
            'pr' => 'Programado',
            default => $entrega->tipo_despacho,
        };

        // Título adaptable según estado — el blade A4 lo usa para la caja del
        // documento (igual que venta/cotización usan "BOLETA", "Proforma", etc.).
        $tipoDocumentoTitulo = match($entrega->estado_entrega) {
            'pe' => 'VALE DE RECOJO',
            'ec' => 'ENTREGA EN CAMINO',
            'en' => 'TICKET DE ENTREGA',
            'ca' => 'ENTREGA CANCELADA',
            default => 'TICKET DE ENTREGA',
        };

        // Datos del cliente — formateado para info-grid del layout compartido.
        $clienteNombre = $cliente
            ? ($cliente->razon_social ?: trim(($cliente->nombres ?? '') . ' ' . ($cliente->apellidos ?? '')) ?: 'CLIENTES VARIOS')
            : 'CLIENTES VARIOS';

        $estadoLabel = match($entrega->estado_entrega) {
            'pe' => 'PENDIENTE',
            'ec' => 'EN CAMINO',
            'en' => 'ENTREGADO',
            'ca' => 'CANCELADO',
            default => strtoupper($entrega->estado_entrega ?? '-'),
        };

        // Filas para info-grid (label => valor) — mismo formato que venta/cotización.
        $filas = [
            [
                'CLIENTE' => $clienteNombre,
                'DOC' => $cliente->numero_documento ?? '-',
            ],
            [
                'TELEFONO' => $cliente->telefono ?? $cliente->celular ?? '-',
                'DIRECCION' => $entrega->direccion_entrega ?? '-',
            ],
            [
                'F. ENTREGA' => $entrega->fecha_entrega
                    ? \Carbon\Carbon::parse($entrega->fecha_entrega)->format('d/m/Y H:i')
                    : '-',
                'ALMACEN' => $entrega->almacenSalida->name ?? '-',
            ],
            [
                'TIPO ENTREGA' => $tipoEntregaLabel,
                'DESPACHADOR' => $entrega->despachador->name ?? '-',
            ],
            [
                'TIPO DESPACHO' => $tipoDespachoLabel,
                'ESTADO' => $estadoLabel,
            ],
        ];

        // Productos anteriores — leemos del último registro de historial
        // con `accion='edicion'`. Si la venta fue editada, `datos_anteriores.productos`
        // contiene la lista que estaba ANTES del último cambio, y `datos_nuevos.productos`
        // la lista actual. El template los muestra como "ANTES → AHORA".
        $productosAnteriores = [];
        $ultimaEdicion = $entrega->venta?->historial?->first();
        if ($ultimaEdicion && is_array($ultimaEdicion->datos_anteriores ?? null)) {
            $productosAnteriores = $this->prepararProductosHistorial(
                $ultimaEdicion->datos_anteriores['productos'] ?? []
            );
        }

        $data = [
            'entrega' => $entrega,
            'empresa' => $empresa,
            'logoPath' => PdfService::getLogoPath($empresa->logo ?? null),
            'cliente' => $cliente,
            'productos' => $productos,
            'productosAnteriores' => $productosAnteriores,
            'fechaUltimaEdicion' => $ultimaEdicion?->fecha?->format('d/m/Y H:i'),
            'nroVenta' => $nroVenta,
            'tipoEntregaLabel' => $tipoEntregaLabel,
            'tipoDespachoLabel' => $tipoDespachoLabel,
            // Campos para el layout compartido (header + info-grid)
            'tipoDocumentoTitulo' => $tipoDocumentoTitulo,
            'numeroDocumento' => $nroVenta,
            'filas' => $filas,
        ];

        // Nombre del archivo según el estado de la entrega — coincide con el
        // título que el blade renderiza adentro.
        $nombreArchivo = match($entrega->estado_entrega) {
            'pe' => "VALE-RECOJO-{$entrega->id}.pdf",
            'ec' => "ENTREGA-EN-CAMINO-{$entrega->id}.pdf",
            'ca' => "ENTREGA-CANCELADA-{$entrega->id}.pdf",
            default => "TICKET-ENTREGA-{$entrega->id}.pdf",
        };

        // Formato A4 (carta) — sin tamaño custom, usa A4 por defecto.
        // Formato ticket (80mm) — tamaño térmico custom.
        if ($formato === 'a4') {
            return PdfService::render(
                'pdf.entrega-a4',
                $data,
                $nombreArchivo,
                'portrait',
            );
        }

        return PdfService::render(
            'pdf.entrega-ticket',
            $data,
            $nombreArchivo,
            'portrait',
            [0, 0, 226.77, 841.89],
        );
    }

    /**
     * Aplana los productos del snapshot de `VentaHistorial` (datos_anteriores
     * o datos_nuevos) a un array plano por unidad derivada, igual formato
     * que `prepararProductos` para que el blade los pueda iterar uniforme.
     *
     * Shape esperado del snapshot (de `VentaController::update`):
     *   productos: [
     *     { nombre, codigo, costo, unidades: [{ unidad, cantidad, precio, ... }] }
     *   ]
     */
    private function prepararProductosHistorial(array $productosSnapshot): array
    {
        $resultado = [];
        foreach ($productosSnapshot as $prod) {
            $unidades = $prod['unidades'] ?? [];
            foreach ($unidades as $ud) {
                $resultado[] = [
                    'codigo' => $prod['codigo'] ?? '',
                    'nombre' => $prod['nombre'] ?? '',
                    'cantidad' => (float) ($ud['cantidad'] ?? 0),
                    'precio' => (float) ($ud['precio'] ?? 0),
                    'unidad' => $ud['unidad'] ?? '',
                ];
            }
        }
        return $resultado;
    }

    private function prepararProductos(EntregaProducto $entrega): array
    {
        $productos = [];
        $yaEntregada = $entrega->estado_entrega === 'en';

        foreach ($entrega->productosEntregados as $detalle) {
            $udv = $detalle->unidadDerivadaVenta;
            $pav = $udv?->productoAlmacenVenta;
            $pa = $pav?->productoAlmacen;
            $producto = $pa?->producto;

            $total = (float) ($udv->cantidad ?? $detalle->cantidad_entregada ?? 0);
            $entregado = $yaEntregada ? $total : 0;
            $pendiente = $total - $entregado;

            $productos[] = [
                'codigo' => $producto->cod_producto ?? '',
                'nombre' => $producto->name ?? '',
                'cantidad' => $total,
                'entregado' => $entregado,
                'pendiente' => $pendiente,
                'unidad' => $udv?->unidadDerivadaInmutable?->name ?? '',
                'ubicacion' => $detalle->ubicacion ?? '',
            ];
        }

        return $productos;
    }
}
