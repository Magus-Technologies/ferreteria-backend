@extends('pdf.layout.document')

@section('content')
    {{-- Header --}}
    @include('pdf.layout.header', [
        'empresa' => $empresa,
        'logoPath' => $logoPath,
        'tipoDocumentoTitulo' => $tipoDocumentoTitulo,
        'numeroDocumento' => $numeroDocumento,
    ])

    {{-- Info del cliente --}}
    @include('pdf.layout.info-grid', ['filas' => $filas])

    {{-- Tabla de productos --}}
    @php
        $filasProductos = [];
        $itemNum = 1;
        foreach ($productos as $p) {
            $filasProductos[] = [
                $itemNum++,
                $p['codigo'],
                $p['nombre'],
                \App\Helpers\Formato::cantidad($p['cantidad']),
                $p['unidad'],
                number_format($p['precio'], 2),
                number_format($p['subtotal'], 2),
            ];
        }
    @endphp
    @include('pdf.layout.table', [
        'columnas' => [
            ['label' => 'ITEM', 'width' => '6%', 'align' => 'center'],
            ['label' => 'CÓDIGO', 'width' => '10%', 'align' => 'center'],
            ['label' => 'DESCRIPCIÓN', 'width' => '40%', 'align' => 'left'],
            ['label' => 'CANT.', 'width' => '8%', 'align' => 'center'],
            ['label' => 'UNIDAD', 'width' => '10%', 'align' => 'center'],
            ['label' => 'P. UNIT.', 'width' => '13%', 'align' => 'right'],
            ['label' => 'IMPORTE', 'width' => '13%', 'align' => 'right'],
        ],
        'filas' => $filasProductos,
        'minFilas' => 8,
    ])

    {{-- SON + Observaciones + Totales --}}
    @include('pdf.layout.totales', [
        'son' => $son,
        'moneda' => 'SOLES',
        'observaciones' => $observaciones,
        'totales' => [
            ['label' => 'SUBTOTAL', 'valor' => number_format($calculos['subtotal'], 2)],
            ['label' => 'IGV (18%)', 'valor' => number_format($calculos['igv'], 2)],
            ['label' => 'TOTAL', 'valor' => number_format($calculos['total'], 2)],
        ],
    ])

    {{-- Footer --}}
    @include('pdf.layout.footer', [
        'codigoQr' => null,
        'mensajeFinal' => 'Representación impresa de la Nota de Débito Electrónica',
    ])
@endsection
