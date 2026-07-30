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
                \App\Helpers\Formato::cantidad($p['cantidad']),
                $p['unidad'],
                $p['nombre'],
                $p['stock_anterior_f'],
                $p['stock_nuevo_f'],
                'S/ ' . number_format($p['costo'], 2),
            ];
        }
    @endphp
    @include('pdf.layout.table', [
        'columnas' => [
            ['label' => 'ITEM', 'width' => '5%', 'align' => 'center'],
            ['label' => 'CÓDIGO', 'width' => '10%', 'align' => 'center'],
            ['label' => 'CANT.', 'width' => '7%', 'align' => 'center'],
            ['label' => 'UNIDAD', 'width' => '10%', 'align' => 'center'],
            ['label' => 'PRODUCTO', 'width' => '30%', 'align' => 'left'],
            ['label' => 'STK ANT.', 'width' => '10%', 'align' => 'center'],
            ['label' => 'STK NVO.', 'width' => '10%', 'align' => 'center'],
            ['label' => 'COSTO', 'width' => '12%', 'align' => 'right'],
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
