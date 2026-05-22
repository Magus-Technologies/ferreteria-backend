<?php

namespace App\Services\Pdf;

use App\Models\EntregaProducto;
use App\Services\Pdf\Traits\ResuelveEstilosPlantilla;
use Illuminate\Http\Response;

class EntregaProductoPdfService
{
    use ResuelveEstilosPlantilla;

    public function generar(int $id, string $formato = 'ticket'): Response
    {
        $entrega = EntregaProducto::with([
            'venta.cliente',
            'venta.entregasProductos',
            'venta.entregasProductos.productosEntregados.unidadDerivadaVenta.productoAlmacenVenta.productoAlmacen.producto',
            'venta.entregasProductos.productosEntregados.unidadDerivadaVenta.unidadDerivadaInmutable',
            // Historial de ediciones de la venta — usado para mostrar
            // "productos anteriores vs actuales" cuando la venta fue editada.
            'venta.historial' => fn ($q) => $q->where('accion', 'edicion')->orderBy('fecha', 'desc'),
            'almacenSalida',
            'despachador',
            'vehiculo',
            'user',
            'userEntregado',
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
        $esParcial = $entrega->tipo_entrega === 'pa';
        $entregasVenta = $entrega->venta?->entregasProductos
            ? $entrega->venta->entregasProductos
                ->sortBy([
                    ['created_at', 'asc'],
                    ['id', 'asc'],
                ])
                ->values()
            : collect();
        $entregasTotales = $esParcial ? 1 : max(1, $entregasVenta->count());
        $entregaNumero = $esParcial
            ? 1
            : max(
                1,
                $entregasVenta->search(fn ($e) => (int) $e->id === (int) $entrega->id) + 1
            );

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
        // con `accion='edicion'`. El objetivo no es mostrar un segundo bloque
        // completo con el snapshot viejo, sino detectar si hubo productos
        // retirados o cantidades reducidas para reflejarlos como "recibido"
        // dentro de la tabla principal.
        $productosAnteriores = [];
        $ultimaEdicion = $entrega->venta?->historial?->first();
        $entregaFueEntregadaAntes = !is_null($entrega->user_entregado_id);
        if ($entregaFueEntregadaAntes && $ultimaEdicion && is_array($ultimaEdicion->datos_anteriores ?? null)) {
            $productosAnteriores = $this->prepararProductosHistorial(
                $ultimaEdicion->datos_anteriores['productos'] ?? []
            );
        }

        $productosTabla = $esParcial
            ? $this->prepararProductosTablaParcialAgrupada($entrega)
            : $this->prepararProductosTabla(
                $productos,
                $productosAnteriores,
                $entregaFueEntregadaAntes
            );
        $mostrarRecibido = collect($productosTabla)->contains(
            fn ($producto) => (float) ($producto['recibido'] ?? 0) > 0
        );

        $formatoPlantilla = $formato === 'a4' ? 'A4' : 'Ticket';
        $estilos = $empresa
            ? $this->prepararDatosPlantilla((int) $empresa->id, 'entrega', $formatoPlantilla)
            : ['est' => [], 'msg' => [], 'bloques' => [], 'font_face_css' => ''];

        $data = array_merge([
            'entrega' => $entrega,
            'empresa' => $empresa,
            'logoPath' => PdfService::getLogoPath($empresa->logo ?? null),
            'cliente' => $cliente,
            'productos' => $productos,
            'productosTabla' => $productosTabla,
            'mostrarRecibido' => $mostrarRecibido,
            'entregaNumero' => $entregaNumero,
            'entregasTotales' => $entregasTotales,
            'fechaUltimaEdicion' => $ultimaEdicion?->fecha?->format('d/m/Y H:i'),
            'nroVenta' => $nroVenta,
            'tipoEntregaLabel' => $tipoEntregaLabel,
            'tipoDespachoLabel' => $tipoDespachoLabel,
            // Campos para el layout compartido (header + info-grid)
            'tipoDocumentoTitulo' => $tipoDocumentoTitulo,
            'numeroDocumento' => $nroVenta,
            'filas' => $filas,
        ], $estilos);

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

    /**
     * Mezcla la entrega actual con el snapshot anterior para mostrar una sola
     * tabla. Si una edición quitó productos o redujo cantidades, eso se
     * expone como "recibido". Si no hubo diferencia real, no se muestra nada
     * adicional y la venta editada se ve igual que una venta normal.
     */
    private function prepararProductosTabla(
        array $productosActuales,
        array $productosAnteriores,
        bool $entregaFueEntregadaAntes
    ): array
    {
        if (!$entregaFueEntregadaAntes || empty($productosAnteriores)) {
            return array_map(function ($producto) {
                return [
                    'codigo' => $producto['codigo'],
                    'nombre' => $producto['nombre'],
                    'unidad' => $producto['unidad'],
                    'recibido' => 0,
                    'entregado' => (float) ($producto['entregado'] ?? 0),
                    'pendiente' => (float) ($producto['pendiente'] ?? 0),
                ];
            }, $productosActuales);
        }

        $anterioresAgrupados = $this->agruparProductosPorClave($productosAnteriores, 'cantidad');
        $actualesAgrupados = $this->agruparProductosPorClave($productosActuales, 'cantidad');

        $filas = [];
        foreach ($actualesAgrupados as $clave => $actual) {
            $cantidadAnterior = (float) ($anterioresAgrupados[$clave]['cantidad'] ?? 0);
            $cantidadActual = (float) ($actual['cantidad'] ?? 0);
            $recibido = max($cantidadAnterior - $cantidadActual, 0);
            $entregadoActual = (float) ($actual['entregado'] ?? 0);
            $pendienteActual = (float) ($actual['pendiente'] ?? 0);
            $entregado = $recibido > 0
                ? max($entregadoActual - min($cantidadAnterior, $cantidadActual), 0)
                : $entregadoActual;

            $filas[] = [
                'codigo' => $actual['codigo'],
                'nombre' => $actual['nombre'],
                'unidad' => $actual['unidad'],
                'recibido' => $recibido,
                // Tras editar una entrega ya confirmada, el documento debe
                // reflejar solo la nueva acción física pendiente: recibir de
                // vuelta o entregar diferencia. Lo que el cliente conserva no
                // se cuenta como "entregado" otra vez.
                'entregado' => $entregado,
                'pendiente' => $pendienteActual,
            ];
        }

        foreach ($anterioresAgrupados as $clave => $anterior) {
            if (isset($actualesAgrupados[$clave])) {
                continue;
            }

            $filas[] = [
                'codigo' => $anterior['codigo'],
                'nombre' => $anterior['nombre'],
                'unidad' => $anterior['unidad'],
                'recibido' => (float) ($anterior['cantidad'] ?? 0),
                'entregado' => 0,
                'pendiente' => 0,
            ];
        }

        return $filas;
    }

    private function agruparProductosPorClave(array $productos, string $campoCantidad): array
    {
        $agrupados = [];

        foreach ($productos as $producto) {
            $clave = $this->productoClave(
                (string) ($producto['codigo'] ?? ''),
                (string) ($producto['unidad'] ?? '')
            );

            if (!isset($agrupados[$clave])) {
                $agrupados[$clave] = [
                    'codigo' => (string) ($producto['codigo'] ?? ''),
                    'nombre' => (string) ($producto['nombre'] ?? ''),
                    'unidad' => (string) ($producto['unidad'] ?? ''),
                    'cantidad' => 0,
                    'entregado' => 0,
                    'pendiente' => 0,
                ];
            }

            $agrupados[$clave]['cantidad'] += (float) ($producto[$campoCantidad] ?? 0);
            $agrupados[$clave]['entregado'] += (float) ($producto['entregado'] ?? 0);
            $agrupados[$clave]['pendiente'] += (float) ($producto['pendiente'] ?? 0);
        }

        return $agrupados;
    }

    private function productoClave(string $codigo, string $unidad): string
    {
        return mb_strtolower(trim($codigo)) . '|' . mb_strtolower(trim($unidad));
    }

    private function prepararProductos(EntregaProducto $entrega): array
    {
        $productos = [];
        $entregaTieneEntregaFisica =
            $entrega->estado_entrega === 'en' || !is_null($entrega->user_entregado_id);

        foreach ($entrega->productosEntregados as $detalle) {
            $udv = $detalle->unidadDerivadaVenta;
            $pav = $udv?->productoAlmacenVenta;
            $pa = $pav?->productoAlmacen;
            $producto = $pa?->producto;

            $cantidadEstaEntrega = (float) ($detalle->cantidad_entregada ?? 0);

            // Recojo en tienda: el PDF debe reflejar el estado REAL de la línea
            // de venta (total / entregado / pendiente), no solo la cantidad del
            // detalle original de la entrega. Esto cubre el caso donde la venta
            // se editó después de una entrega parcial en tienda.
            if ($entrega->tipo_entrega === 'rt' && $udv) {
                $total = (float) ($udv->cantidad ?? $cantidadEstaEntrega);
                $pendiente = max(0.0, (float) ($udv->cantidad_pendiente ?? 0));
                $entregado = max(0.0, $total - $pendiente);
            } else {
                $total = $cantidadEstaEntrega;
                $pendiente = $entregaTieneEntregaFisica ? 0.0 : $cantidadEstaEntrega;
                $entregado = $entregaTieneEntregaFisica ? $cantidadEstaEntrega : 0.0;
            }

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

    private function prepararProductosTablaParcialAgrupada(EntregaProducto $entrega): array
    {
        $grupoId = $entrega->grupo_entrega_id ?: $entrega->id;
        $entregasGrupo = ($entrega->venta?->entregasProductos ?? collect())
            ->filter(function ($hija) use ($grupoId) {
                if ($hija->grupo_entrega_id) {
                    return (int) $hija->grupo_entrega_id === (int) $grupoId;
                }
                return $hija->tipo_entrega === 'pa' && (int) $hija->venta_id === (int) $entrega->venta_id;
            })
            ->values();

        $mapa = [];

        foreach ($entregasGrupo as $hija) {
            foreach ($hija->productosEntregados as $detalle) {
                $udv = $detalle->unidadDerivadaVenta;
                $producto = $udv?->productoAlmacenVenta?->productoAlmacen?->producto;
                $unidad = $udv?->unidadDerivadaInmutable?->name ?? '';
                $codigo = $producto->cod_producto ?? '';
                $clave = $this->productoClave((string) $codigo, (string) $unidad);

                if (!isset($mapa[$clave])) {
                    $mapa[$clave] = [
                        'codigo' => (string) $codigo,
                        'nombre' => (string) ($producto->name ?? ''),
                        'unidad' => (string) $unidad,
                        'recibido' => 0,
                        'total' => (float) ($udv?->cantidad ?? 0),
                        'entregado' => 0.0,
                        'pendiente' => 0.0,
                    ];
                }

                $mapa[$clave]['total'] = max(
                    (float) $mapa[$clave]['total'],
                    (float) ($udv?->cantidad ?? 0)
                );

                if ($hija->estado_entrega === 'en') {
                    $mapa[$clave]['entregado'] += (float) ($detalle->cantidad_entregada ?? 0);
                }
            }
        }

        foreach ($mapa as &$fila) {
            $fila['pendiente'] = max(0, (float) $fila['total'] - (float) $fila['entregado']);
            unset($fila['total']);
        }
        unset($fila);

        return array_values($mapa);
    }
}
