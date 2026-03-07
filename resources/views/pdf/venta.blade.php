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
    @include('pdf.layout.table', [
        'columnas' => [
            ['label' => 'ITEM', 'width' => '5%', 'align' => 'center'],
            ['label' => 'UBI.', 'width' => '5%', 'align' => 'center'],
            ['label' => 'CODIGO', 'width' => '10%', 'align' => 'center'],
            ['label' => 'CANT.', 'width' => '7%', 'align' => 'center'],
            ['label' => 'UNIDAD', 'width' => '8%', 'align' => 'center'],
            ['label' => 'DESCRIPCION', 'width' => '40%', 'align' => 'left'],
            ['label' => 'P. UNI.', 'width' => '10%', 'align' => 'right'],
            ['label' => 'DESC.', 'width' => '7%', 'align' => 'right'],
            ['label' => 'IMPORTE', 'width' => '8%', 'align' => 'right'],
        ],
        'filas' => collect($productos)->map(function ($p, $i) {
            return [
                $i + 1,
                'A1',
                $p['codigo'],
                number_format($p['cantidad'], 0),
                $p['unidad'],
                $p['nombre'],
                number_format($p['precio'], 2),
                number_format($p['descuento'], 2),
                number_format($p['subtotal'], 2),
            ];
        })->toArray(),
        'minFilas' => 10,
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
        'codigoQr' => $codigoQr,
        'mensajeFinal' => 'GRACIAS POR SU PREFERENCIA! DIOS LES BENDIGA!',
    ])
@endsection
