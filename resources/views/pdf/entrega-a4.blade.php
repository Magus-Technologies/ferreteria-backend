@extends('pdf.layout.document')

@section('content')
    {{-- Header con logo + empresa + caja del documento (mismo layout
         compartido que venta.blade.php y cotizacion.blade.php). --}}
    @include('pdf.layout.header', [
        'empresa' => $empresa,
        'logoPath' => $logoPath,
        'tipoDocumentoTitulo' => $tipoDocumentoTitulo,
        'numeroDocumento' => $numeroDocumento,
    ])

    {{-- Info del cliente y de la entrega — usa el info-grid amarillo
         (color tema #fadc06) igual que las ventas. --}}
    @include('pdf.layout.info-grid', ['filas' => $filas])

    {{-- Tabla de productos — muestra entregado vs pendiente según estado --}}
    @include('pdf.layout.table', [
        'columnas' => [
            ['label' => 'ITEM', 'width' => '5%', 'align' => 'center'],
            ['label' => 'CODIGO', 'width' => '13%', 'align' => 'center'],
            ['label' => 'DESCRIPCION', 'width' => '42%', 'align' => 'left'],
            ['label' => 'UNIDAD', 'width' => '12%', 'align' => 'center'],
            ['label' => 'ENTREGADO', 'width' => '14%', 'align' => 'center'],
            ['label' => 'PENDIENTE', 'width' => '14%', 'align' => 'center'],
        ],
        'filas' => collect($productos)->map(function ($p, $i) {
            return [
                $i + 1,
                $p['codigo'],
                $p['nombre'],
                $p['unidad'],
                number_format($p['entregado'], 2),
                number_format($p['pendiente'], 2),
            ];
        })->toArray(),
        'minFilas' => 10,
    ])

    {{-- Total ítems (no hay totales monetarios porque la entrega no
         maneja dinero — eso vive en la boleta). --}}
    <table style="width: 100%; margin-top: 8px; border: 2px solid #fadc06;">
        <tr style="background-color: #fadc06;">
            <td style="padding: 6px 8px; font-size: 8pt; font-weight: bold; text-align: right;">
                TOTAL ITEMS
            </td>
            <td style="padding: 6px 8px; font-size: 8pt; font-weight: bold; text-align: right; width: 80px;">
                {{ count($productos) }}
            </td>
        </tr>
    </table>

    {{-- Observaciones --}}
    @if($entrega->observaciones)
        <div style="margin-top: 10px; font-size: 7pt;">
            <span style="font-weight: bold;">OBSERVACIONES:</span>
            {{ $entrega->observaciones }}
        </div>
    @endif

    {{-- Firmas --}}
    <table style="width: 100%; margin-top: 35px;">
        <tr>
            <td style="width: 45%; text-align: center; border-top: 1px solid #000; padding-top: 4px; font-size: 7pt;">
                Firma del Despachador
            </td>
            <td style="width: 10%;"></td>
            <td style="width: 45%; text-align: center; border-top: 1px solid #000; padding-top: 4px; font-size: 7pt;">
                Firma del Cliente
            </td>
        </tr>
    </table>

    {{-- Mensaje final + footer (sin QR porque no es comprobante SUNAT). --}}
    <div style="text-align: center; margin-top: 15px; font-size: 8pt; font-weight: bold;">
        GRACIAS POR SU PREFERENCIA! DIOS LES BENDIGA!
    </div>
    <div style="text-align: center; font-size: 6pt; color: #666; margin-top: 4px;">
        Documento interno de entrega — no tiene validez fiscal.
    </div>
@endsection
