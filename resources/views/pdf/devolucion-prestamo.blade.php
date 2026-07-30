@extends('pdf.layout.document')

@section('content')
    @php
        $colorTema = $est['color_tema'] ?? '#fadc06';
        $colorBorde = $est['color_borde'] ?? '#fadc06';
        $borderThin = $est['border_thin_px'] ?? 1;
        $borderPx = $est['border_px'] ?? 2;
        $padPx = $est['pad_px'] ?? 4;

        $cssObsLabel = $bloques['obs_label']['css'] ?? '';
        $cssObsValor = $bloques['obs_valor']['css'] ?? '';

        $docLabel = strlen($entidadDocumento) === 11 ? 'RUC' : 'DNI';
    @endphp

    {{-- Header (mantiene la caja amarilla del tema) --}}
    @include('pdf.layout.header', [
        'empresa' => $empresa,
        'logoPath' => $logoPath,
        'tipoDocumentoTitulo' => $tipoDocumentoTitulo,
        'numeroDocumento' => $numeroDocumento,
    ])

    {{-- Info general + entidad --}}
    @include('pdf.layout.info-grid', ['filas' => $filas])

    {{-- Texto intro propio de la devolución --}}
    <div style="font-size: 8pt; margin-bottom: 6px; margin-top: 4px;">
        Por medio del presente documento se deja constancia de la devolución de los siguientes bienes,
        correspondientes al préstamo N° {{ $prestamo->numero }}:
    </div>

    {{-- Tabla de productos devueltos --}}
    @php
        $headerColumnas = [
            ['label' => 'ITEM', 'width' => '8%', 'align' => 'center'],
            ['label' => 'CODIGO', 'width' => '15%', 'align' => 'center'],
            ['label' => 'DESCRIPCION', 'width' => 'auto', 'align' => 'left'],
            ['label' => 'UNIDAD', 'width' => '15%', 'align' => 'center'],
            ['label' => 'CANTIDAD', 'width' => '15%', 'align' => 'center'],
        ];

        $filasProductos = collect($productos)->map(function ($p, $i) {
            return [
                $i + 1,
                $p['codigo'],
                $p['nombre'],
                $p['unidad'],
                \App\Helpers\Formato::cantidad($p['cantidad']),
            ];
        })->toArray();
    @endphp
    @include('pdf.layout.table', [
        'columnas' => $headerColumnas,
        'filas' => $filasProductos,
        'minFilas' => 8,
    ])

    {{-- Observaciones --}}
    <table style="width: 100%; margin-top: 8px; border-collapse: collapse;">
        <tr>
            <td style="vertical-align: top;">
                <div style="border: {{ $borderPx }}px solid {{ $colorBorde }}; padding: 8px; border-radius: 6px; min-height: 60px;">
                    <div style="{{ $cssObsLabel }} margin-bottom: 4px;">OBSERVACIONES:</div>
                    <div style="{{ $cssObsValor }} line-height: 1.5;">
                        {{ $observaciones }}
                    </div>
                </div>
            </td>
        </tr>
    </table>

    {{-- Firmas --}}
    <table style="width: 100%; margin-top: 40px; border-collapse: collapse;">
        <tr>
            <td style="width: 50%; text-align: center; padding: 0 20px;">
                <div style="border-top: 1.5px solid {{ $colorBorde }}; padding-top: 4px; font-size: 8pt; font-weight: bold; text-transform: uppercase;">
                    {{ $esCliente ? 'Entregado por el Cliente' : 'Recibido por el Proveedor' }}
                </div>
                <div style="font-size: 7pt; margin-top: 2px;">
                    {{ $entidadNombre }}
                </div>
                @if($entidadDocumento)
                    <div style="font-size: 7pt;">{{ $docLabel }}: {{ $entidadDocumento }}</div>
                @endif
            </td>
            <td style="width: 50%; text-align: center; padding: 0 20px;">
                <div style="border-top: 1.5px solid {{ $colorBorde }}; padding-top: 4px; font-size: 8pt; font-weight: bold; text-transform: uppercase;">
                    {{ $esCliente ? 'Recibido por la Empresa' : 'Entregado por la Empresa' }}
                </div>
                <div style="font-size: 7pt; margin-top: 2px;">
                    {{ $empresa->razon_social }}
                </div>
                <div style="font-size: 7pt;">R.U.C. {{ $empresa->ruc }}</div>
            </td>
        </tr>
    </table>
@endsection
