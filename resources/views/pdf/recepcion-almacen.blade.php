@extends('pdf.layout.document')

@section('content')
    {{-- Header --}}
    @include('pdf.layout.header', [
        'empresa' => $empresa,
        'logoPath' => $logoPath,
        'tipoDocumentoTitulo' => $tipoDocumentoTitulo,
        'numeroDocumento' => $numeroDocumento,
    ])

    {{-- Info General --}}
    @include('pdf.layout.info-grid', ['filas' => $filas])

    {{-- Datos del Proveedor --}}
    <div style="font-size: 9pt; font-weight: bold; padding-left: 8px; padding-top: 4px; padding-bottom: 2px;">
        Datos del Proveedor
    </div>
    @include('pdf.layout.info-grid', ['filas' => $filasProveedor])

    {{-- Datos del Transportista --}}
    <div style="font-size: 9pt; font-weight: bold; padding-left: 8px; padding-top: 4px; padding-bottom: 2px;">
        Datos del Transportista
    </div>
    @include('pdf.layout.info-grid', ['filas' => $filasTransportista])

    {{-- Tabla de Productos --}}
    @php
        $filasProductos = [];
        $itemNum = 1;
        foreach ($productos as $p) {
            $filasProductos[] = [
                $itemNum++,
                $p['codigo'],
                ($p['bonificacion'] ? '* ' : '') . $p['nombre'],
                $p['unidad'],
                number_format($p['cantidad'], 0),
                number_format($p['stock_anterior'], 0),
                number_format($p['stock_nuevo'], 0),
            ];
        }
    @endphp
    @include('pdf.layout.table', [
        'columnas' => [
            ['label' => 'ITEM', 'width' => '5%', 'align' => 'center'],
            ['label' => 'CÓDIGO', 'width' => '12%', 'align' => 'center'],
            ['label' => 'PRODUCTO', 'width' => '35%', 'align' => 'left'],
            ['label' => 'UNIDAD', 'width' => '12%', 'align' => 'center'],
            ['label' => 'CANT.', 'width' => '10%', 'align' => 'center'],
            ['label' => 'STK ANT.', 'width' => '13%', 'align' => 'center'],
            ['label' => 'STK NVO.', 'width' => '13%', 'align' => 'center'],
        ],
        'filas' => $filasProductos,
        'minFilas' => 10,
    ])

    {{-- Totales --}}
    @include('pdf.layout.totales', [
        'son' => '',
        'moneda' => '',
        'observaciones' => $observaciones,
        'totales' => [
            ['label' => 'TOTAL ITEMS', 'valor' => number_format($total, 0)],
        ],
    ])

    {{-- Footer --}}
    @include('pdf.layout.footer', [
        'codigoQr' => null,
        'mensajeFinal' => '',
    ])
@endsection
