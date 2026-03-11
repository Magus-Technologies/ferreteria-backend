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
        $filasProductos = [];
        $itemNum = 1;
        foreach ($productos as $p) {
            $filasProductos[] = [
                $itemNum++,
                $p['codigo'],
                $p['nombre'],
                $p['marca'],
                $p['unidad'],
                number_format($p['cantidad'], 0),
                $monedaSimbolo . ' ' . number_format($p['precio'], 2),
                $monedaSimbolo . ' ' . number_format($p['flete'], 2),
                $monedaSimbolo . ' ' . number_format($p['subtotal'], 2),
            ];
        }
    @endphp
    @include('pdf.layout.table', [
        'columnas' => [
            ['label' => 'ITEM', 'width' => '5%', 'align' => 'center'],
            ['label' => 'CÓDIGO', 'width' => '10%', 'align' => 'center'],
            ['label' => 'DESCRIPCIÓN', 'width' => '30%', 'align' => 'left'],
            ['label' => 'MARCA', 'width' => '10%', 'align' => 'center'],
            ['label' => 'UNID.', 'width' => '7%', 'align' => 'center'],
            ['label' => 'CANT.', 'width' => '7%', 'align' => 'center'],
            ['label' => 'P. UNIT.', 'width' => '10%', 'align' => 'right'],
            ['label' => 'FLETE', 'width' => '10%', 'align' => 'right'],
            ['label' => 'SUBTOTAL', 'width' => '11%', 'align' => 'right'],
        ],
        'filas' => $filasProductos,
        'minFilas' => 8,
    ])

    {{-- SON + Observaciones + Totales --}}
    @php
        $totalItems = [
            ['label' => 'SUBTOTAL', 'valor' => $monedaSimbolo . ' ' . number_format($calculos['subtotal'], 2)],
        ];
        if ($calculos['flete_total'] > 0) {
            $totalItems[] = ['label' => 'FLETE TOTAL', 'valor' => $monedaSimbolo . ' ' . number_format($calculos['flete_total'], 2)];
        }
        if ($calculos['percepcion'] > 0) {
            $totalItems[] = ['label' => 'PERCEPCIÓN', 'valor' => $monedaSimbolo . ' ' . number_format($calculos['percepcion'], 2)];
        }
        $totalItems[] = ['label' => 'TOTAL', 'valor' => $monedaSimbolo . ' ' . number_format($calculos['total'], 2)];
    @endphp
    @include('pdf.layout.totales', [
        'son' => $son,
        'moneda' => $moneda,
        'observaciones' => $observaciones,
        'totales' => $totalItems,
    ])

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
