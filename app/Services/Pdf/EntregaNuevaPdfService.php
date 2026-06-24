<?php

namespace App\Services\Pdf;

use App\Models\Entrega;
use App\Services\Pdf\Traits\ResuelveEstilosPlantilla;
use Illuminate\Http\Response;

/**
 * Genera el PDF de ticket/A4 para el nuevo modelo Entrega
 * (sistema de entregas reestructurado — tabla `entrega`).
 *
 * Reutiliza las mismas blade templates que EntregaProductoPdfService
 * (pdf.entrega-ticket / pdf.entrega-a4) adaptando el modelo nuevo.
 */
class EntregaNuevaPdfService
{
    use ResuelveEstilosPlantilla;

    public function generar(int $id, string $formato = 'ticket'): Response
    {
        $entrega = Entrega::with([
            'venta.cliente',
            'venta.historial',
            'estadoEntrega',
            'tipoEntrega',
            'tipoDespacho',
            'almacenSalida:id,name',
            'chofer:id,name',
            'userEntregado:id,name',
            'userCreador:id,name,empresa_id',
            'userCreador.empresa',
            'venta.user.empresa',
            'detalles.unidadDerivadaVenta.unidadDerivadaInmutable',
            'detalles.unidadDerivadaVenta.productoAlmacenVenta.productoAlmacen.producto',
        ])->findOrFail($id);

        // ── Empresa ──────────────────────────────────────────────
        $empresa = $entrega->userCreador?->empresa
            ?? $entrega->venta?->user?->empresa
            ?? null;

        if (!$empresa) {
            $entrega->load('userCreador.empresa', 'venta.user.empresa');
            $empresa = $entrega->userCreador?->empresa
                ?? $entrega->venta?->user?->empresa;
        }

        // ── Cliente ──────────────────────────────────────────────
        $cliente = $entrega->venta?->cliente ?? null;

        // ── Estado (string código) ────────────────────────────────
        $estadoCodigo = $entrega->estadoEntrega?->codigo?->value ?? 'pe';

        // ── Adaptador para las blade templates ────────────────────
        // Las plantillas acceden a $entrega->estado_entrega, ->fecha_entrega,
        // ->despachador, ->user, ->productosEntregados, etc. como el modelo viejo.
        $adapter = new \stdClass();
        $adapter->id                 = $entrega->id;
        $adapter->estado_entrega     = $estadoCodigo;
        $adapter->fecha_entrega      = $entrega->fecha_ejecutada;
        $adapter->fecha_programada   = $entrega->fecha_programada;
        $adapter->hora_inicio        = $entrega->hora_inicio;
        $adapter->hora_fin           = $entrega->hora_fin;
        $adapter->direccion_entrega  = $entrega->direccion_entrega;
        $adapter->observaciones      = $entrega->observaciones;
        $adapter->tipo_entrega       = $entrega->tipoEntrega?->codigo?->value ?? '';
        $adapter->tipo_despacho      = $entrega->tipoDespacho?->codigo?->value ?? '';
        $adapter->user_entregado_id  = $entrega->user_entregado_id;
        $adapter->grupo_entrega_id   = null; // no existe en el nuevo modelo
        $adapter->despachador        = $entrega->chofer ?? $entrega->userEntregado;
        $adapter->user               = $entrega->userCreador;
        $adapter->almacenSalida      = $entrega->almacenSalida;
        $adapter->venta              = $entrega->venta;
        // Mapea detalles → formato esperado por prepararProductos
        $adapter->productosEntregados = $entrega->detalles->map(function ($d) {
            $obj = new \stdClass();
            $obj->cantidad_entregada  = $d->cantidad;
            $obj->ubicacion           = $d->ubicacion;
            $obj->unidadDerivadaVenta = $d->unidadDerivadaVenta;
            return $obj;
        });

        // ── Etiquetas ────────────────────────────────────────────
        $tipoEntregaLabel = match($adapter->tipo_entrega) {
            'rt' => 'Recojo en Tienda',
            'de' => 'Despacho a Domicilio',
            'pa' => 'Parcial',
            default => $entrega->tipoEntrega?->nombre ?? '—',
        };

        $tipoDespachoLabel = match($adapter->tipo_despacho) {
            'in' => 'Inmediato',
            'pr' => 'Programado',
            default => $entrega->tipoDespacho?->nombre ?? '—',
        };

        $tipoDocumentoTitulo = match($estadoCodigo) {
            'pe' => match($adapter->tipo_entrega) {
                'de'  => 'VALE DE DESPACHO',
                'pa'  => 'VALE DE ENTREGA PARCIAL',
                default => 'VALE DE RECOJO',
            },
            'ec' => 'ENTREGA EN CAMINO',
            'en' => 'TICKET DE ENTREGA',
            'ca' => 'ENTREGA CANCELADA',
            default => 'TICKET DE ENTREGA',
        };

        $estadoLabel = match($estadoCodigo) {
            'pe' => 'PENDIENTE',
            'ec' => 'EN CAMINO',
            'en' => 'ENTREGADO',
            'ca' => 'CANCELADO',
            default => strtoupper($estadoCodigo),
        };

        // ── Número de venta ──────────────────────────────────────
        $serie   = $entrega->venta?->serie ?? '';
        $numero  = $entrega->venta?->numero ?? '';
        $nroVenta = $serie && $numero ? "{$serie}-{$numero}" : '—';

        // ── Cliente: nombre y datos ───────────────────────────────
        $clienteNombre = $cliente
            ? ($cliente->razon_social ?: trim(($cliente->nombres ?? '') . ' ' . ($cliente->apellidos ?? '')) ?: 'CLIENTES VARIOS')
            : 'CLIENTES VARIOS';

        $filasCliente = [
            ['CLIENTE' => $clienteNombre, 'DOC' => $cliente?->numero_documento ?? '-'],
            ['TELEFONO' => $cliente?->telefono ?? $cliente?->celular ?? '-', 'DIRECCION' => $adapter->direccion_entrega ?? '-'],
        ];

        $filasEntrega = [
            [
                'F. ENTREGA' => $adapter->fecha_entrega
                    ? \Carbon\Carbon::parse($adapter->fecha_entrega)->format('d/m/Y H:i')
                    : ($adapter->fecha_programada ? \Carbon\Carbon::parse($adapter->fecha_programada)->format('d/m/Y') : '-'),
                'ALMACEN' => $adapter->almacenSalida?->name ?? '-',
            ],
            ['TIPO ENTREGA' => $tipoEntregaLabel, 'DESPACHADOR' => $adapter->despachador?->name ?? $entrega->userEntregado?->name ?? $adapter->user?->name ?? '-'],
            ['TIPO DESPACHO' => $tipoDespachoLabel, 'ESTADO' => $estadoLabel],
        ];

        // ── Recibido: detectar edición posterior a entrega física ──
        // user_entregado_id se preserva cuando el backend resetea 'en'→'pe' al editar
        // la venta, por lo que no basta con verificar $estadoCodigo === 'en'.
        $fueEntregadaFisicamente = $estadoCodigo === 'en' || $entrega->user_entregado_id !== null;
        $ultimaEdicion = null;
        if ($fueEntregadaFisicamente) {
            $ultimaEdicion = $entrega->venta?->historial
                ?->sortByDesc('created_at')
                ->first(fn($h) => $h->accion === 'edicion');
        }
        $mostrarRecibido = $fueEntregadaFisicamente && $ultimaEdicion !== null;

        // ── Productos (tabla) ─────────────────────────────────────
        $productosTabla = $this->prepararProductosDesdeDetalles($entrega, $estadoCodigo, $ultimaEdicion);

        // Recojo en tienda ('rt') usa sus propios blades y su propio comprobante
        // de estilos ('recojo'); el resto usa los de entrega.
        $esRecojo = $adapter->tipo_entrega === 'rt';

        // ── Estilos ───────────────────────────────────────────────
        $formatoPlantilla = $formato === 'a4' ? 'A4' : 'Ticket';
        $comprobanteEstilos = $esRecojo ? 'recojo' : 'entrega';
        $estilos = $empresa
            ? $this->prepararDatosPlantilla((int) $empresa->id, $comprobanteEstilos, $formatoPlantilla)
            : ['est' => [], 'msg' => [], 'bloques' => [], 'font_face_css' => ''];

        $data = array_merge([
            'entrega'             => $adapter,
            'empresa'             => $empresa,
            'logoPath'            => PdfService::getLogoPath($empresa?->logo ?? null),
            'cliente'             => $cliente,
            'productos'           => $productosTabla,
            'productosTabla'      => $productosTabla,
            'mostrarRecibido'     => $mostrarRecibido,
            'entregaNumero'       => $entrega->venta_entrega_secuencia ?? 1,
            'entregasTotales'     => null, // no calculamos el total de entregas del grupo
            'fechaUltimaEdicion'  => null,
            'nroVenta'            => $nroVenta,
            'tipoEntregaLabel'    => $tipoEntregaLabel,
            'tipoDespachoLabel'   => $tipoDespachoLabel,
            'tipoDocumentoTitulo' => $tipoDocumentoTitulo,
            'numeroDocumento'     => $nroVenta,
            'filas'               => array_merge($filasCliente, $filasEntrega),
            'filasCliente'        => $filasCliente,
            'filasEntrega'        => $filasEntrega,
        ], $estilos);

        $nombreArchivo = match($estadoCodigo) {
            'pe' => "VALE-RECOJO-{$id}.pdf",
            'ec' => "ENTREGA-EN-CAMINO-{$id}.pdf",
            'ca' => "ENTREGA-CANCELADA-{$id}.pdf",
            default => "TICKET-ENTREGA-{$id}.pdf",
        };

        // Recojo en tienda usa sus propios blades (recojo-*); el resto
        // (despacho a domicilio / parcial / en camino / entregado / cancelado)
        // usa los blades de entrega (entrega-*).
        $vistaA4     = $esRecojo ? 'pdf.recojo-a4' : 'pdf.entrega-a4';
        $vistaTicket = $esRecojo ? 'pdf.recojo-ticket' : 'pdf.entrega-ticket';

        if ($formato === 'a4') {
            return PdfService::render($vistaA4, $data, $nombreArchivo, 'portrait');
        }

        return PdfService::render($vistaTicket, $data, $nombreArchivo, 'portrait', [0, 0, 226.77, 841.89]);
    }

    private function prepararProductosDesdeDetalles(Entrega $entrega, string $estadoCodigo, ?object $ultimaEdicion = null): array
    {
        $entregaFisica = $estadoCodigo === 'en' || $entrega->user_entregado_id !== null;

        $prevQuantities = [];
        $prevProductos  = [];
        if ($entregaFisica && $ultimaEdicion) {
            $datosAnteriores = is_array($ultimaEdicion->datos_anteriores)
                ? $ultimaEdicion->datos_anteriores
                : json_decode($ultimaEdicion->datos_anteriores ?? '{}', true);
            foreach ($datosAnteriores['productos'] ?? [] as $ph) {
                foreach ($ph['unidades'] ?? [] as $ud) {
                    $key = strtolower(trim($ph['codigo'] ?? '')) . '|' . strtolower(trim($ud['unidad'] ?? ''));
                    $qty = (float) ($ud['cantidad'] ?? 0);
                    $prevQuantities[$key] = $qty;
                    $prevProductos[$key]  = [
                        'nombre'   => $ph['nombre'] ?? '—',
                        'codigo'   => $ph['codigo'] ?? '',
                        'unidad'   => $ud['unidad'] ?? '',
                        'cantidad' => $qty,
                    ];
                }
            }
        }

        $tabla = [];
        foreach ($entrega->detalles as $detalle) {
            $udv      = $detalle->unidadDerivadaVenta;
            $pav      = $udv?->productoAlmacenVenta;
            $pa       = $pav?->productoAlmacen;
            $producto = $pa?->producto;

            $codigo      = $producto?->cod_producto ?? '';
            $unidad      = $udv?->unidadDerivadaInmutable?->name ?? '';
            $udvCantidad = (float) ($udv?->cantidad ?? 0);
            $detalleQty  = (float) ($detalle->cantidad ?? 0);

            $key             = strtolower(trim($codigo)) . '|' . strtolower(trim($unidad));
            $cantidadAnterior = $prevQuantities[$key] ?? $udvCantidad;
            $recibido        = max($cantidadAnterior - $udvCantidad, 0);

            if ($ultimaEdicion && $recibido > 0) {
                // Decrease: units were returned — show original delivery, net delivered, recibido.
                $cantidad  = $detalleQty;
                $entregado = $udvCantidad;
                $pendiente = 0.0;
            } elseif ($ultimaEdicion) {
                // Increase or same-qty edit.
                $cantidad = max($cantidadAnterior, $udvCantidad);
                $recibido = 0.0;
                if ($udvCantidad > $cantidadAnterior && $estadoCodigo === 'en') {
                    // Re-confirmed after increase: all units now delivered.
                    $entregado = $entregaFisica ? $udvCantidad : 0.0;
                    $pendiente = 0.0;
                } else {
                    // Pending after increase, or same-qty edit.
                    $entregado = $entregaFisica ? min($cantidadAnterior, $udvCantidad) : 0.0;
                    $pendiente = $entregaFisica ? max($udvCantidad - $cantidadAnterior, 0.0) : $udvCantidad;
                }
            } else {
                // Normal case (no edit): Total = what this delivery was programmed for.
                $cantidad  = $detalleQty;
                $entregado = $entregaFisica ? $detalleQty : 0.0;
                $pendiente = $entregaFisica ? 0.0 : $detalleQty;
                $recibido  = 0.0;
            }

            $tabla[] = [
                'codigo'    => $codigo,
                'nombre'    => $producto?->name ?? '—',
                'cantidad'  => $cantidad,
                'entregado' => $entregado,
                'pendiente' => $pendiente,
                'recibido'  => $recibido,
                'unidad'    => $unidad,
                'ubicacion' => $detalle->ubicacion ?? '',
            ];
        }

        // Products removed from the venta after this delivery was created have their
        // entrega_detalle cascade-deleted, so they don't appear in the loop above.
        // Recover them from datos_anteriores and show them as fully "recibido".
        if ($entregaFisica && $ultimaEdicion && !empty($prevProductos)) {
            $processedKeys = [];
            foreach ($tabla as $fila) {
                $k = strtolower(trim($fila['codigo'])) . '|' . strtolower(trim($fila['unidad']));
                $processedKeys[$k] = true;
            }
            foreach ($prevProductos as $key => $prev) {
                if (!isset($processedKeys[$key])) {
                    $tabla[] = [
                        'codigo'    => $prev['codigo'],
                        'nombre'    => $prev['nombre'],
                        'cantidad'  => $prev['cantidad'],
                        'entregado' => 0.0,
                        'pendiente' => 0.0,
                        'recibido'  => $prev['cantidad'],
                        'unidad'    => $prev['unidad'],
                        'ubicacion' => '',
                    ];
                }
            }
        }

        return $tabla;
    }
}
