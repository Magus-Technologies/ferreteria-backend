@extends('pdf.layout.document')

@section('content')
    {{-- Header --}}
    @include('pdf.layout.header', [
        'empresa' => $empresa,
        'logoPath' => $logoPath,
        'tipoDocumentoTitulo' => $tipoDocumentoTitulo,
        'numeroDocumento' => $numeroDocumento,
    ])

    {{-- Info de la guia --}}
    @include('pdf.layout.info-grid', ['filas' => $filas])

    {{-- Tabla de detalles --}}
    @include('pdf.layout.table', [
        'columnas' => [
            ['label' => 'ITEM', 'width' => '5%', 'align' => 'center'],
            ['label' => 'CODIGO', 'width' => '12%', 'align' => 'center'],
            ['label' => 'DESCRIPCION', 'width' => '48%', 'align' => 'left'],
            ['label' => 'CANT.', 'width' => '10%', 'align' => 'center'],
            ['label' => 'UNIDAD', 'width' => '12%', 'align' => 'center'],
            ['label' => 'PESO (KG)', 'width' => '13%', 'align' => 'right'],
        ],
        'filas' => collect($detalles)->map(function ($d, $i) {
            return [
                $i + 1,
                $d['codigo'],
                $d['nombre'],
                \App\Helpers\Formato::cantidad($d['cantidad']),
                $d['unidad'],
                $d['peso'] > 0 ? number_format($d['peso'], 2) : '-',
            ];
        })->toArray(),
        'minFilas' => 10,
    ])

    {{-- Peso total --}}
    <div style="text-align: right; margin-top: 6px; font-size: 8pt;">
        <strong>PESO TOTAL:</strong> {{ number_format($pesoTotal, 2) }} KG
    </div>

    {{-- Observaciones + QR --}}
    <div style="display: flex; margin-top: 8px;">
        <table style="width: 100%;">
            <tr>
                <td style="width: 75%; vertical-align: top; font-size: 7pt; padding-right: 10px;">
                    <strong>Observaciones:</strong><br>
                    {{ $observaciones }}
                </td>
                @if($codigoQr)
                <td style="width: 25%; text-align: center; vertical-align: top;">
                    <img src="{{ $codigoQr }}" style="width: 80px; height: 80px;" alt="QR">
                    <div style="font-size: 5pt; color: #666; margin-top: 2px;">
                        Representacion impresa del comprobante electronico
                    </div>
                </td>
                @endif
            </tr>
        </table>
    </div>

    @if(!empty($consultaUrl))
    <div style="text-align: center; margin-top: 8px; padding-top: 5px; border-top: 1px solid #ddd;">
        <span style="font-size: 7pt; color: #666;">Consulte su documento en: </span>
        <span style="font-size: 7pt; font-weight: bold; color: #333;">{{ $consultaUrl }}</span>
    </div>
    @endif
@endsection
