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

    {{-- Tabla de Productos --}}
    @php
        $filasProductos = [];
        $itemNum = 1;
        foreach ($productos as $p) {
            $filasProductos[] = [
                $itemNum++,
                $p['codigo'],
                number_format($p['cantidad'], 3),
                $p['unidad'],
                $p['nombre'],
                $p['stk_ant_origen_f'],
                $p['stk_nvo_origen_f'],
                $p['stk_ant_destino_f'],
                $p['stk_nvo_destino_f'],
                'S/ ' . number_format($p['costo_total'], 2),
            ];
        }
    @endphp
    @include('pdf.layout.table', [
        'columnas' => [
            ['label' => 'ITEM', 'width' => '4%', 'align' => 'center'],
            ['label' => 'CÓDIGO', 'width' => '9%', 'align' => 'center'],
            ['label' => 'CANT.', 'width' => '6%', 'align' => 'center'],
            ['label' => 'UNIDAD', 'width' => '9%', 'align' => 'center'],
            ['label' => 'PRODUCTO', 'width' => '22%', 'align' => 'left'],
            ['label' => 'STK ANT. ORIG.', 'width' => '10%', 'align' => 'center'],
            ['label' => 'STK NVO. ORIG.', 'width' => '10%', 'align' => 'center'],
            ['label' => 'STK ANT. DEST.', 'width' => '10%', 'align' => 'center'],
            ['label' => 'STK NVO. DEST.', 'width' => '10%', 'align' => 'center'],
            ['label' => 'COSTO', 'width' => '10%', 'align' => 'right'],
        ],
        'filas' => $filasProductos,
        'minFilas' => 10,
    ])

    {{-- SON + Observaciones + Totales --}}
    @include('pdf.layout.totales', [
        'son' => $son,
        'moneda' => 'SOLES',
        'observaciones' => $observaciones,
        'totales' => [
            ['label' => 'TOTAL', 'valor' => 'S/ ' . number_format($total, 2)],
        ],
    ])

    {{-- Footer --}}
    @include('pdf.layout.footer', [
        'codigoQr' => null,
        'mensajeFinal' => '',
    ])
@endsection
