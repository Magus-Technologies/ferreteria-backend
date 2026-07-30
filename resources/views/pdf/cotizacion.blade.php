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
                \App\Helpers\Formato::cantidad($p['cantidad']),
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
        'totales' => array_values(array_filter([
            ['label' => 'SUBTOTAL', 'valor' => number_format($calculos['subtotal'], 2)],
            ['label' => 'T. DESCUENTO', 'valor' => number_format($calculos['total_descuento'], 2)],
            // IGV solo se muestra cuando el cliente es RUC.
            !empty($esRuc) ? ['label' => 'IGV (18%)', 'valor' => number_format($calculos['igv'] ?? 0, 2)] : null,
            ['label' => 'TOTAL', 'valor' => number_format($calculos['total'], 2)],
        ])),
    ])

    {{-- Footer --}}
    @if(empty($msg['ocultar_despedida']))
    <div style="{{ $bloques['despedida_footer']['css'] ?? 'font-size: 8pt; text-align: center; font-weight: bold;' }} margin-top: 15px;">
        Sin otro particular, esperando su pronta respuesta.
        @if(($plantilla->despedida_activo ?? false) && !empty($plantilla->mensaje_despedida))
            {!! $plantilla->mensaje_despedida !!}
        @else
            GRACIAS POR SU PREFERENCIA! DIOS LES BENDIGA!
        @endif
    </div>
    @endif
    @if(empty($msg['ocultar_canjear']))
    <div style="{{ $bloques['despedida_footer']['css'] ?? 'font-size: 7pt; text-align: center; font-weight: bold;' }} margin-bottom: 15px;">
        - CANJEAR POR BOLETA O FACTURA -
    </div>
    @endif

    {{-- Tabla de cuentas bancarias --}}
    @if(empty($msg['ocultar_cuentas_bancarias']))
        @include('pdf.layout.cuentas-bancarias', ['est' => $est, 'bloques' => $bloques])
    @endif
@endsection
