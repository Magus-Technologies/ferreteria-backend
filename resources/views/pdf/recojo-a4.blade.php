@extends('pdf.layout.document')

@section('content')
    {{-- Blade exclusivo para RECOJO EN TIENDA (vale de recojo / constancia de
         recojo). Comparte el layout con entrega-a4 pero se separa para poder
         personalizarlo de forma independiente. El título lo decide el service
         vía $tipoDocumentoTitulo. --}}
    @include('pdf.layout.header', [
        'empresa' => $empresa,
        'logoPath' => $logoPath,
        'tipoDocumentoTitulo' => $tipoDocumentoTitulo,
        'numeroDocumento' => $numeroDocumento,
    ])

    {{-- Datos del cliente (usa info_label / info_valor) --}}
    @include('pdf.layout.info-grid', ['filas' => $filasCliente ?? $filas, 'sinMargen' => true])

    {{-- Datos de la entrega (usa entrega_info_label / entrega_info_valor para
         poder personalizar estos estilos independientemente del cliente) --}}
    @php
        $colorBorde = $est['color_borde'] ?? '#fadc06';
        $padPx = $est['pad_px'] ?? 4;
        $borderThin = $est['border_thin_px'] ?? 1;
        $cssEntregaLabel = $bloques['entrega_info_label']['css'] ?? '';
        $cssEntregaValor = $bloques['entrega_info_valor']['css'] ?? '';
        $filasEntregaList = $filasEntrega ?? [];
    @endphp
    @if(count($filasEntregaList) > 0)
        <table style="width: 100%; border: {{ $borderThin }}px solid {{ $colorBorde }}; border-top: 0; margin-bottom: 10px; border-collapse: collapse;">
            @foreach($filasEntregaList as $fila)
                <tr style="min-height: 18px;">
                    @php($celdas = 0)
                    @foreach($fila as $label => $valor)
                        <td style="{{ $cssEntregaLabel }} width: {{ $loop->first ? '12%' : '15%' }}; padding: {{ $padPx }}px;">
                            {{ $label }}
                        </td>
                        <td style="{{ $cssEntregaValor }} width: {{ $loop->first ? '38%' : '35%' }}; padding: {{ $padPx }}px;">
                            : {{ $valor }}
                        </td>
                        @php($celdas++)
                    @endforeach
                    @if($celdas < 2)
                        @for($i = $celdas; $i < 2; $i++)
                            <td style="width: 15%; padding: {{ $padPx }}px;"></td>
                            <td style="width: 35%; padding: {{ $padPx }}px;"></td>
                        @endfor
                    @endif
                </tr>
            @endforeach
        </table>
    @endif

    @if(($entregasTotales ?? 1) > 1)
        <div style="margin: 8px 0 10px; padding: 6px 10px; border: 1px solid #fadc06; background: #fff9db; font-size: 8pt; font-weight: bold; text-align: center;">
            ENTREGA {{ $entregaNumero }} DE {{ $entregasTotales }}
        </div>
    @endif

    {{-- Tabla de productos — si la venta fue editada y realmente se retiró un
         producto o cantidad, se muestra en la columna "RECIBIDO" dentro de la
         misma tabla principal. --}}
    @include('pdf.layout.table', [
        'columnas' => $mostrarRecibido
            ? [
                ['label' => 'ITEM', 'width' => '5%', 'align' => 'center'],
                ['label' => 'CODIGO', 'width' => '12%', 'align' => 'center'],
                ['label' => 'DESCRIPCION', 'width' => '37%', 'align' => 'left'],
                ['label' => 'UNIDAD', 'width' => '10%', 'align' => 'center'],
                ['label' => 'RECIBIDO', 'width' => '12%', 'align' => 'center'],
                ['label' => 'ENTREGADO', 'width' => '12%', 'align' => 'center'],
                ['label' => 'PENDIENTE', 'width' => '12%', 'align' => 'center'],
            ]
            : [
                ['label' => 'ITEM', 'width' => '5%', 'align' => 'center'],
                ['label' => 'CODIGO', 'width' => '13%', 'align' => 'center'],
                ['label' => 'DESCRIPCION', 'width' => '42%', 'align' => 'left'],
                ['label' => 'UNIDAD', 'width' => '12%', 'align' => 'center'],
                ['label' => 'ENTREGADO', 'width' => '14%', 'align' => 'center'],
                ['label' => 'PENDIENTE', 'width' => '14%', 'align' => 'center'],
            ],
        'filas' => collect($productosTabla)->map(function ($p, $i) use ($mostrarRecibido) {
            return [
                ...($mostrarRecibido
                    ? [
                        $i + 1,
                        $p['codigo'],
                        $p['nombre'],
                        $p['unidad'],
                        number_format($p['recibido'], 2),
                        number_format($p['entregado'], 2),
                        number_format($p['pendiente'], 2),
                    ]
                    : [
                        $i + 1,
                        $p['codigo'],
                        $p['nombre'],
                        $p['unidad'],
                        number_format($p['entregado'], 2),
                        number_format($p['pendiente'], 2),
                    ]),
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
                {{ count($productosTabla) }}
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
        Documento interno de recojo — no tiene validez fiscal.
    </div>
@endsection
