@extends('pdf.layout.document')

@section('content')
    {{-- Header --}}
    @include('pdf.layout.header', [
        'empresa' => $empresa,
        'logoPath' => $logoPath,
        'tipoDocumentoTitulo' => $tipoDocumentoTitulo,
        'numeroDocumento' => $numeroDocumento,
    ])

    {{-- Info del proveedor --}}
    @include('pdf.layout.info-grid', ['filas' => $filas])

    {{-- Tabla de productos --}}
    @include('pdf.layout.table', [
        'columnas' => [
            ['label' => 'CODIGO', 'width' => '10%', 'align' => 'center'],
            ['label' => 'DESCRIPCION', 'width' => '35%', 'align' => 'left'],
            ['label' => 'MARCA', 'width' => '12%', 'align' => 'center'],
            ['label' => 'U.MEDIDA', 'width' => '10%', 'align' => 'center'],
            ['label' => 'CANTIDAD', 'width' => '10%', 'align' => 'right'],
            ['label' => 'COSTO', 'width' => '13%', 'align' => 'right'],
            ['label' => 'SUBTOTAL', 'width' => '10%', 'align' => 'right'],
        ],
        'filas' => collect($productos)->map(function ($p) {
            return [
                $p['codigo'],
                $p['nombre'],
                $p['marca'],
                $p['unidad'],
                number_format($p['cantidad'], 2),
                number_format($p['costo'], 5),
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
        'mensajeFinalHtml' => (!empty($plantilla) && $plantilla->despedida_activo)
            ? $plantilla->mensaje_despedida
            : null,
        'mensajeFinal' => 'GRACIAS POR SU PREFERENCIA!',
        'consultaUrl' => $consultaUrl ?? null,
    ])
@endsection
