<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $titulo ?? 'Ticket' }}</title>
    @php
        $est = $est ?? [];
        $bloques = $bloques ?? [];
        $colorBorde = $est['color_borde'] ?? '#000';
        $borderThin = $est['border_thin_px'] ?? 1;
        $padPx = $est['pad_px'] ?? 4;
        $fuente = $est['fuente'] ?? 'Helvetica';
        $fontPt = $est['font_pt'] ?? 7;

        $cssEmpresaRazon = $bloques['empresa_razon']['css'] ?? '';
        $cssEmpresaDir = $bloques['empresa_direccion']['css'] ?? '';
        $cssCajaRuc = $bloques['caja_ruc']['css'] ?? '';
        $cssCajaTipo = $bloques['caja_tipo']['css'] ?? '';
        $cssCajaNumero = $bloques['caja_numero']['css'] ?? '';
        $cssInfoLabel = $bloques['info_label']['css'] ?? '';
        $cssInfoValor = $bloques['info_valor']['css'] ?? '';
        $cssTablaHeader = $bloques['tabla_header']['css'] ?? '';
        $cssTablaFila = $bloques['tabla_fila']['css'] ?? '';
        $cssTotalLabel = $bloques['total_label']['css'] ?? '';
        $cssTotalValor = $bloques['total_valor']['css'] ?? '';
        $cssObsLabel = $bloques['obs_label']['css'] ?? '';
        $cssObsValor = $bloques['obs_valor']['css'] ?? '';
    @endphp
    <style>
        @page {
            size: 80mm auto;
            margin: 3mm;
        }
        {!! $font_face_css ?? '' !!}
        body {
            font-family: "{{ $fuente }}", Arial, sans-serif;
            font-size: {{ $fontPt }}pt;
            color: #000;
            line-height: 1.3;
            width: 74mm;
            margin: 0;
            padding: 0;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .separator { border-top: {{ $borderThin }}px dashed {{ $colorBorde }}; margin: 4px 0; }
        table { width: 100%; border-collapse: collapse; }
    </style>
</head>
<body>
    {{-- Header: Logo + Empresa --}}
    <div class="text-center" style="margin-bottom: 4px;">
        @if($logoPath && !($msg['ocultar_logo'] ?? false))
            <img src="{{ $logoPath }}" style="max-height: 120px; max-width: 180px;" alt="Logo">
        @endif
        <div style="margin-top: 2px;">
            <div style="{{ $cssEmpresaRazon }}">{{ $empresa->razon_social }}</div>
            <div style="{{ $cssCajaRuc }}">R.U.C. {{ $empresa->ruc }}</div>
            <div style="{{ $cssEmpresaDir }}">{{ $empresa->direccion }}</div>
            <div style="{{ $cssEmpresaDir }}"><strong>Cel:</strong> {{ $empresa->telefono }}</div>
            <div style="{{ $cssEmpresaDir }}"><strong>Email:</strong> {{ $empresa->email }}</div>
        </div>
    </div>

    <div class="separator"></div>

    {{-- Tipo documento y numero --}}
    <div class="text-center" style="padding: 4px 0;">
        <div style="{{ $cssCajaTipo }}">{{ $tipoOperacion }}</div>
        <div style="{{ $cssCajaNumero }}">{{ $numeroDocumento }}</div>
    </div>

    <div class="separator"></div>

    {{-- Info del prestamo --}}
    <div style="padding: 2px 0 6px;">
        <table>
            <tr>
                <td style="{{ $cssInfoLabel }} width: 50%;">F. Emisi&oacute;n: <span style="{{ $cssInfoValor }}">{{ $fechaEmision }}</span></td>
                <td style="{{ $cssInfoLabel }} width: 50%;">Hora: <span style="{{ $cssInfoValor }}">{{ $hora }}</span></td>
            </tr>
            <tr>
                <td style="{{ $cssInfoLabel }}">F. Vencimiento:</td>
                <td style="{{ $cssInfoValor }}">{{ $fechaVencimiento }}</td>
            </tr>
            <tr>
                <td style="{{ $cssInfoLabel }}">Vendedor:</td>
                <td style="{{ $cssInfoValor }}">{{ $vendedor }}</td>
            </tr>
            <tr>
                <td style="{{ $cssInfoLabel }}">Estado:</td>
                <td style="{{ $cssInfoValor }}">{{ $estado }}</td>
            </tr>
            <tr>
                <td style="{{ $cssInfoLabel }}">Moneda:</td>
                <td style="{{ $cssInfoValor }}">{{ $monedaNombre }}</td>
            </tr>
            @if($tasaInteres)
            <tr>
                <td style="{{ $cssInfoLabel }}">Tasa Inter&eacute;s:</td>
                <td style="{{ $cssInfoValor }}">{{ $tasaInteres }}</td>
            </tr>
            @endif
        </table>
    </div>

    <div class="separator"></div>

    {{-- Info de la entidad --}}
    <div style="padding: 2px 0 6px;">
        <table>
            <tr>
                <td style="{{ $cssInfoLabel }}">{{ $esCliente ? 'Cliente:' : 'Proveedor:' }}</td>
                <td style="{{ $cssInfoValor }}">{{ $entidadNombre }}</td>
            </tr>
            <tr>
                <td style="{{ $cssInfoLabel }}">{{ strlen($entidadDocumento) === 11 ? 'RUC:' : 'DNI:' }}</td>
                <td style="{{ $cssInfoValor }}">{{ $entidadDocumento }}</td>
            </tr>
            @if($entidadDireccion)
            <tr>
                <td style="{{ $cssInfoLabel }}">Direcci&oacute;n:</td>
                <td style="{{ $cssInfoValor }}">{{ $entidadDireccion }}</td>
            </tr>
            @endif
        </table>
    </div>

    <div class="separator"></div>

    {{-- Tabla de productos --}}
    @php
        $colsSel = $columnas ?? ['producto', 'cantidad', 'unidad', 'costo', 'importe'];
        $colsTicket = [
            'producto' => 'Descripci&oacute;n',
            'cantidad' => 'Cant.',
            'unidad' => 'Unid.',
            'costo' => 'Costo',
            'importe' => 'Importe',
        ];
        $colsActivas = array_filter(array_keys($colsTicket), fn($k) => in_array($k, $colsSel));
        if (empty($colsActivas)) $colsActivas = ['producto'];
    @endphp
    <div style="padding-top: 4px;">
        <table>
            <thead>
                <tr style="border-bottom: {{ $borderThin }}px solid {{ $colorBorde }};">
                    @foreach($colsActivas as $c)
                    <th style="{{ $cssTablaHeader }} text-align: left;">{!! $colsTicket[$c] !!}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($productos as $i => $p)
                <tr style="border-bottom: {{ $borderThin }}px solid {{ $colorBorde }};{{ $i % 2 !== 0 ? ' background-color: #f9f9f9;' : '' }}">
                    @foreach($colsActivas as $c)
                    <td style="{{ $cssTablaFila }} padding: 3px 0;">
                        @if($c === 'producto'){{ $p['nombre'] }}
                        @elseif($c === 'cantidad'){{ number_format($p['cantidad'], 0) }}
                        @elseif($c === 'unidad'){{ $p['unidad'] }}
                        @elseif($c === 'costo'){{ number_format($p['costo'], 2) }}
                        @elseif($c === 'importe'){{ number_format($p['subtotal'], 2) }}
                        @endif
                    </td>
                    @endforeach
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Totales --}}
    @php
        $verTotal = $columnas === null || in_array('monto_total', $columnas);
        $verPagado = $columnas === null || in_array('monto_pagado', $columnas);
        $verSaldo = $columnas === null || in_array('saldo_pendiente', $columnas);
    @endphp
    <div style="margin-top: 4px;">
        <table>
            @if($verTotal)
            <tr style="border-bottom: {{ $borderThin }}px solid {{ $colorBorde }};">
                <td style="{{ $cssTotalLabel }}">MONTO TOTAL</td>
                <td style="{{ $cssTotalValor }} text-align: right;">{{ $monedaSymbol }} {{ number_format($montoTotal, 2) }}</td>
            </tr>
            @endif
            @if($verPagado)
            <tr style="border-bottom: {{ $borderThin }}px solid {{ $colorBorde }};">
                <td style="{{ $cssTotalLabel }}">MONTO PAGADO</td>
                <td style="{{ $cssTotalValor }} text-align: right;">{{ $monedaSymbol }} {{ number_format($montoPagado, 2) }}</td>
            </tr>
            @endif
            @if($verSaldo)
            <tr>
                <td style="{{ $cssTotalLabel }}">SALDO PENDIENTE</td>
                <td style="{{ $cssTotalValor }} text-align: right;">{{ $monedaSymbol }} {{ number_format($montoPendiente, 2) }}</td>
            </tr>
            @endif
        </table>
    </div>

    {{-- Observaciones --}}
    <div style="margin-top: 4px;">
        <div style="{{ $cssObsLabel }}">Observaciones:</div>
        <div style="{{ $cssObsValor }}">{{ $observaciones }}</div>
    </div>

    @if($garantia)
    <div style="margin-top: 2px;">
        <span style="{{ $cssInfoLabel }}">Garant&iacute;a:</span>
        <span style="{{ $cssInfoValor }}">{{ $garantia }}</span>
    </div>
    @endif
</body>
</html>
