@extends('pdf.layout.document')

@section('content')
    {{-- Header --}}
    @include('pdf.layout.header', [
        'empresa' => $empresa,
        'logoPath' => $logoPath,
        'tipoDocumentoTitulo' => $tipoDocumentoTitulo,
        'numeroDocumento' => $numeroDocumento,
    ])

    {{-- Información General --}}
    @include('pdf.layout.info-grid', ['filas' => $filas])

    {{-- Proveedor --}}
    <div style="font-size: 9pt; font-weight: bold; padding-left: 8px; padding-top: 4px; padding-bottom: 2px;">
        Proveedor
    </div>
    @include('pdf.layout.info-grid', ['filas' => $filasProveedor])

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
        
        if (in_array('subtotal', $columnasSeleccionadas)) {
            $totalItems[] = ['label' => 'SUBTOTAL', 'valor' => $monedaSimbolo . ' ' . number_format($calculos['subtotal'], 2)];
        }
        if ($calculos['flete_total'] > 0 && in_array('flete', $columnasSeleccionadas)) {
            $totalItems[] = ['label' => 'FLETE TOTAL', 'valor' => $monedaSimbolo . ' ' . number_format($calculos['flete_total'], 2)];
        }
        if ($calculos['percepcion'] > 0 && in_array('total', $columnasSeleccionadas)) {
            $totalItems[] = ['label' => 'PERCEPCIÓN', 'valor' => $monedaSimbolo . ' ' . number_format($calculos['percepcion'], 2)];
        }
        if (in_array('total', $columnasSeleccionadas)) {
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

    {{-- Condiciones de Pago --}}
    <div style="margin-top: 10px; font-size: 9pt; font-weight: bold; padding-left: 8px; padding-bottom: 2px;">
        Condiciones de Pago
    </div>
    @include('pdf.layout.info-grid', ['filas' => $filasPago])

    {{-- Footer --}}
    @include('pdf.layout.footer', [
        'codigoQr' => null,
        'mensajeFinal' => '',
    ])
@endsection
