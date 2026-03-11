@extends('pdf.layout.document')

@section('content')
    {{-- Header --}}
    @include('pdf.layout.header', [
        'empresa' => $empresa,
        'logoPath' => $logoPath,
        'tipoDocumentoTitulo' => $tipoDocumentoTitulo,
        'numeroDocumento' => $numeroDocumento,
    ])

    {{-- Info de la entidad --}}
    @include('pdf.layout.info-grid', ['filas' => $filas])

    {{-- Texto intro --}}
    <div style="font-size: 7pt; margin-bottom: 8px; margin-top: 5px;">
        De nuestra consideracion: Por medio de la presente se detalla el siguiente {{ strtolower($tipoOperacion) }}:
    </div>

    {{-- Tabla de productos --}}
    @php $almacenNombre = $prestamo->almacen->name ?? 'A1'; @endphp
    @include('pdf.layout.table', [
        'columnas' => [
            ['label' => 'ITEM', 'width' => '5%', 'align' => 'center'],
            ['label' => 'UBI.', 'width' => '5%', 'align' => 'center'],
            ['label' => 'CODIGO', 'width' => '10%', 'align' => 'center'],
            ['label' => 'CANT.', 'width' => '7%', 'align' => 'center'],
            ['label' => 'UNIDAD', 'width' => '8%', 'align' => 'center'],
            ['label' => 'DESCRIPCION', 'width' => '40%', 'align' => 'left'],
            ['label' => 'COSTO UNI.', 'width' => '10%', 'align' => 'right'],
            ['label' => '-', 'width' => '7%', 'align' => 'right'],
            ['label' => 'IMPORTE', 'width' => '8%', 'align' => 'right'],
        ],
        'filas' => collect($productos)->map(function ($p, $i) use ($almacenNombre) {
            return [
                $i + 1,
                $almacenNombre,
                $p['codigo'],
                number_format($p['cantidad'], 0),
                $p['unidad'],
                $p['nombre'],
                number_format($p['costo'], 2),
                '-',
                number_format($p['subtotal'], 2),
            ];
        })->toArray(),
        'minFilas' => 10,
    ])

    {{-- SON + Observaciones + Totales --}}
    @include('pdf.layout.totales', [
        'son' => 'TOTAL DEL ' . strtoupper($tipoOperacion),
        'moneda' => '',
        'observaciones' => $observaciones,
        'totales' => $totales,
    ])

    {{-- Footer --}}
    <div style="text-align: center; margin-top: 15px; font-size: 8pt;">
        Sin otro particular, esperando su pronta respuesta.
        <span style="font-weight: bold;">GRACIAS POR SU PREFERENCIA! DIOS LES BENDIGA!</span>
    </div>

    {{-- Tabla de cuentas bancarias --}}
    @include('pdf.layout.cuentas-bancarias')
@endsection
