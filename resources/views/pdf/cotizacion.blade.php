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

    {{-- Texto intro --}}
    <div style="font-size: 7pt; margin-bottom: 8px; margin-top: 5px;">
        De nuestra consideracion: Por medio de la presente es grato saludarlos
        y a la vez cotizarle los siguientes productos:
    </div>

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
        'moneda' => $moneda,
        'observaciones' => $observaciones,
        'totales' => [
            ['label' => 'SUBTOTAL', 'valor' => number_format($calculos['subtotal'], 2)],
            ['label' => 'T. DESCUENTO', 'valor' => number_format($calculos['total_descuento'], 2)],
            ['label' => 'TOTAL', 'valor' => number_format($calculos['total'], 2)],
        ],
    ])

    {{-- Footer --}}
    <div style="text-align: center; margin-top: 15px; font-size: 8pt;">
        Sin otro particular, esperando su pronta respuesta.
        <span style="font-weight: bold;">GRACIAS POR SU PREFERENCIA! DIOS LES BENDIGA!</span>
    </div>
    <div style="text-align: center; font-size: 7pt; margin-bottom: 15px;">
        - CANJEAR POR BOLETA O FACTURA -
    </div>

    {{-- Tabla de cuentas bancarias --}}
    @include('pdf.layout.cuentas-bancarias')
@endsection
