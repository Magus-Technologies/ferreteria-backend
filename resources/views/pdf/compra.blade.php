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

    {{-- Observaciones --}}
    <div style="margin-top: 10px;">
        <div style="border: 2px solid #fadc06; border-radius: 8px; padding: 8px; width: 60%; display: inline-block;">
            <div style="font-size: 8pt; font-weight: bold; margin-bottom: 4px;">OBSERVACIONES</div>
            <div style="font-size: 6pt; line-height: 1.5;">{{ $observaciones }}</div>
        </div>
    </div>

    {{-- Totales alineados a la derecha --}}
    <table style="width: 100%; margin-top: 10px;">
        <tr>
            <td style="width: 70%;"></td>
            <td style="width: 30%;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr style="border-bottom: 1px solid #ccc;">
                        <td style="padding: 4px; font-size: 9pt; font-weight: bold;">SUBTOTAL:</td>
                        <td style="padding: 4px; font-size: 9pt; text-align: right;">
                            S/ {{ number_format($calculos['subtotal'], 2) }}
                        </td>
                    </tr>
                    <tr style="border-bottom: 1px solid #ccc;">
                        <td style="padding: 4px; font-size: 9pt; font-weight: bold;">IGV (18%):</td>
                        <td style="padding: 4px; font-size: 9pt; text-align: right;">
                            S/ {{ number_format($calculos['igv'], 2) }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
@endsection
