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
            'estadoEntrega',
            'tipoEntrega',
            'tipoDespacho',
            'almacenSalida:id,name',
            'chofer:id,name',
            'userCreador:id,name',
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
        $adapter->despachador        = $entrega->chofer;
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
            'pe' => 'VALE DE RECOJO',
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
            ['TIPO ENTREGA' => $tipoEntregaLabel, 'DESPACHADOR' => $adapter->despachador?->name ?? $adapter->user?->name ?? '-'],
            ['TIPO DESPACHO' => $tipoDespachoLabel, 'ESTADO' => $estadoLabel],
        ];

        // ── Productos (tabla) ─────────────────────────────────────
        $productosTabla = $this->prepararProductosDesdeDetalles($entrega, $estadoCodigo);

        // ── Estilos ───────────────────────────────────────────────
        $formatoPlantilla = $formato === 'a4' ? 'A4' : 'Ticket';
        $estilos = $empresa
            ? $this->prepararDatosPlantilla((int) $empresa->id, 'entrega', $formatoPlantilla)
            : ['est' => [], 'msg' => [], 'bloques' => [], 'font_face_css' => ''];

        $data = array_merge([
            'entrega'             => $adapter,
            'empresa'             => $empresa,
            'logoPath'            => PdfService::getLogoPath($empresa?->logo ?? null),
            'cliente'             => $cliente,
            'productos'           => $productosTabla,
            'productosTabla'      => $productosTabla,
            'mostrarRecibido'     => false,
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

        if ($formato === 'a4') {
            return PdfService::render('pdf.entrega-a4', $data, $nombreArchivo, 'portrait');
        }

        return PdfService::render('pdf.entrega-ticket', $data, $nombreArchivo, 'portrait', [0, 0, 226.77, 841.89]);
    }

    private function prepararProductosDesdeDetalles(Entrega $entrega, string $estadoCodigo): array
    {
        $entregaFisica = $estadoCodigo === 'en' || !is_null($entrega->user_entregado_id);

        $tabla = [];
        foreach ($entrega->detalles as $detalle) {
            $udv      = $detalle->unidadDerivadaVenta;
            $pav      = $udv?->productoAlmacenVenta;
            $pa       = $pav?->productoAlmacen;
            $producto = $pa?->producto;

            $cantidad = (float) ($detalle->cantidad ?? 0);

            if ($udv) {
                $total     = (float) ($udv->cantidad ?? $cantidad);
                $pendiente = (float) ($udv->cantidad_pendiente ?? ($entregaFisica ? 0 : $cantidad));
                $entregado = $total - $pendiente;
            } else {
                $total     = $cantidad;
                $pendiente = $entregaFisica ? 0.0 : $cantidad;
                $entregado = $entregaFisica ? $cantidad : 0.0;
            }

            $tabla[] = [
                'codigo'    => $producto?->cod_producto ?? '',
                'nombre'    => $producto?->name ?? '—',
                'cantidad'  => $total,
                'entregado' => $entregado,
                'pendiente' => $pendiente,
                'recibido'  => 0,
                'unidad'    => $udv?->unidadDerivadaInmutable?->name ?? '',
                'ubicacion' => $detalle->ubicacion ?? '',
            ];
        }

        return $tabla;
    }
}
