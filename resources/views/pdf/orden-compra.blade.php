@extends('pdf.layout.document')

@section('content')
    {{-- Header --}}
    @include('pdf.layout.header', [
        'empresa' => $empresa,
        'logoPath' => $logoPath,
        'tipoDocumentoTitulo' => $tipoDocumentoTitulo,
        'numeroDocumento' => $numeroDocumento,
    ])

    {{-- Información General + Proveedor (un solo recuadro continuo) --}}
    @php
        $filasInfoProveedor = array_merge(
            $filas,
            [['__titulo' => 'Proveedor']],
            $filasProveedor
        );
    @endphp
    @include('pdf.layout.info-grid', ['filas' => $filasInfoProveedor])

    {{-- Tabla de Productos --}}
    @php
        $columnasSeleccionadas = $columnas ?? [
            'codigo', 'producto', 'marca', 'unidad', 'cantidad', 'precio', 'flete', 'subtotal', 'total'
        ];

        $filasProductos = [];
        $itemNum = 1;
        foreach ($productos as $p) {
            $fila = [$itemNum++];
            if (in_array('codigo', $columnasSeleccionadas)) $fila[] = $p['codigo'];
            if (in_array('producto', $columnasSeleccionadas)) $fila[] = $p['nombre'];
            if (in_array('marca', $columnasSeleccionadas)) $fila[] = $p['marca'];
            if (in_array('unidad', $columnasSeleccionadas)) $fila[] = $p['unidad'];
            if (in_array('cantidad', $columnasSeleccionadas)) $fila[] = number_format($p['cantidad'], 2);
            if (in_array('precio', $columnasSeleccionadas)) $fila[] = $monedaSimbolo . ' ' . number_format($p['precio'], 2);
            if (in_array('flete', $columnasSeleccionadas)) $fila[] = $monedaSimbolo . ' ' . number_format($p['flete'], 2);
            if (in_array('subtotal', $columnasSeleccionadas)) $fila[] = $monedaSimbolo . ' ' . number_format($p['subtotal'], 2);
            
            $filasProductos[] = $fila;
        }

        $headerColumnas = [
            ['label' => 'ITEM', 'width' => '5%', 'align' => 'center']
        ];
        if (in_array('codigo', $columnasSeleccionadas)) $headerColumnas[] = ['label' => 'CÓDIGO', 'width' => '12%', 'align' => 'center'];
        if (in_array('producto', $columnasSeleccionadas)) $headerColumnas[] = ['label' => 'DESCRIPCIÓN', 'width' => 'auto', 'align' => 'left'];
        if (in_array('marca', $columnasSeleccionadas)) $headerColumnas[] = ['label' => 'MARCA', 'width' => '10%', 'align' => 'center'];
        if (in_array('unidad', $columnasSeleccionadas)) $headerColumnas[] = ['label' => 'UNID.', 'width' => '8%', 'align' => 'center'];
        if (in_array('cantidad', $columnasSeleccionadas)) $headerColumnas[] = ['label' => 'CANT.', 'width' => '8%', 'align' => 'center'];
        if (in_array('precio', $columnasSeleccionadas)) $headerColumnas[] = ['label' => 'P. UNIT.', 'width' => '11%', 'align' => 'right'];
        if (in_array('flete', $columnasSeleccionadas)) $headerColumnas[] = ['label' => 'FLETE', 'width' => '11%', 'align' => 'right'];
        if (in_array('subtotal', $columnasSeleccionadas)) $headerColumnas[] = ['label' => 'SUBTOTAL', 'width' => '11%', 'align' => 'right'];
    @endphp
    @include('pdf.layout.table', [
        'columnas' => $headerColumnas,
        'filas' => $filasProductos,
        'minFilas' => 8,
    ])

    {{-- SON + Observaciones + Totales --}}
    @php
        $totalItems = [];
        
        // Sin renglón de SUBTOTAL: el desagregado por impuesto (OP. GRAVADAS +
        // I.G.V.) ya deja claro cómo se compone el total, y repetir el subtotal
        // arriba solo confundía.
        if ($calculos['flete_total'] > 0 && in_array('flete', $columnasSeleccionadas)) {
            $totalItems[] = ['label' => 'FLETE TOTAL', 'valor' => $monedaSimbolo . ' ' . number_format($calculos['flete_total'], 2)];
        }
        if ($calculos['percepcion'] > 0 && in_array('total', $columnasSeleccionadas)) {
            $totalItems[] = ['label' => 'PERCEPCIÓN', 'valor' => $monedaSimbolo . ' ' . number_format($calculos['percepcion'], 2)];
        }
        if (in_array('total', $columnasSeleccionadas)) {
            // Desagregado del impuesto: los precios ya incluyen IGV, así que
            // OP. GRAVADAS + I.G.V. dan exactamente el TOTAL de abajo.
            $totalItems[] = ['label' => 'OP. GRAVADAS', 'valor' => $monedaSimbolo . ' ' . number_format($calculos['op_gravadas'], 2)];
            $totalItems[] = ['label' => 'I.G.V. ' . $calculos['igv_porcentaje'] . '%', 'valor' => $monedaSimbolo . ' ' . number_format($calculos['igv'], 2)];
            $totalItems[] = ['label' => 'TOTAL', 'valor' => $monedaSimbolo . ' ' . number_format($calculos['total'], 2)];
        }
        
        $mostrarTablaTotales = count($totalItems) > 0;
    @endphp

    @if($mostrarTablaTotales)
        @include('pdf.layout.totales', [
            'son' => $son,
            'moneda' => $moneda,
            'observaciones' => $observaciones,
            'totales' => $totalItems,
        ])
    @else
        <div style="margin-top: 15px; font-size: 9pt;">
            <strong>Observaciones:</strong> {{ $observaciones }}
        </div>
    @endif

    {{-- Condiciones de Pago (con título dentro del recuadro) --}}
    @php
        $filasCondPago = array_merge(
            [['__titulo' => 'Condiciones de Pago']],
            $filasPago
        );
    @endphp
    @include('pdf.layout.info-grid', ['filas' => $filasCondPago])

    {{-- Firma: la línea, y debajo el nombre y el cargo de quien firma. Ambos textos
         salen de `mensajes_extra`, así que se pueden cambiar desde la configuración
         de plantillas sin tocar la vista. --}}
    <table style="width: 100%; margin-top: 30px;">
        <tr>
            <td style="width: 35%;"></td>
            <td style="width: 30%; text-align: center; vertical-align: bottom;">
                {{-- Línea de firma inline: la clase `.signature-line` está definida
                     dentro de otra plantilla, no en el layout compartido. El
                     margen superior reserva el espacio para firmar a mano. --}}
                <div style="border-top: 1px solid #000; width: 100%; margin-top: 42px; margin-bottom: 6px;"></div>
                <div style="font-size: 8pt; font-weight: bold; text-transform: uppercase;">
                    {{ $msg['firma_nombre'] ?? '' }}
                </div>
                <div style="font-size: 7.5pt; text-transform: uppercase;">
                    {{ $msg['firma_cargo'] ?? '' }}
                </div>
            </td>
            <td style="width: 35%;"></td>
        </tr>
    </table>

    {{-- Footer --}}
    @include('pdf.layout.footer', [
        'codigoQr' => null,
        'mensajeFinal' => '',
    ])
@endsection
