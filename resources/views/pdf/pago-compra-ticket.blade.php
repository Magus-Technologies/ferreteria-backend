<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Comprobante de Pago</title>
    @php
        $est = $est ?? [];
        $bloques = $bloques ?? [];
        $colorBorde = $est['color_borde'] ?? '#000';
        $borderThin = $est['border_thin_px'] ?? 1;
        $borderPx = $est['border_px'] ?? 2;
        $padPx = $est['pad_px'] ?? 4;
        $fuente = $est['fuente'] ?? 'Helvetica';
        $fontPt = $est['font_pt'] ?? 7;

        $cssCajaTipo = $bloques['caja_tipo']['css'] ?? '';
        $cssCajaNumero = $bloques['caja_numero']['css'] ?? '';
        $cssInfoLabel = $bloques['info_label']['css'] ?? '';
        $cssInfoValor = $bloques['info_valor']['css'] ?? '';
        $cssTotalLabel = $bloques['total_label']['css'] ?? '';
        $cssTotalValor = $bloques['total_valor']['css'] ?? '';
        $cssDespedida = $bloques['despedida_footer']['css'] ?? '';
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
        .separator-double { border-top: {{ $borderPx }}px solid {{ $colorBorde }}; margin: 4px 0; }
        table { width: 100%; border-collapse: collapse; }
    </style>
</head>
<body>
    <div class="text-center" style="margin-bottom: 4px;">
        @if($logoPath && !($msg['ocultar_logo'] ?? false))
            <img src="{{ $logoPath }}" style="max-height: 120px; max-width: 180px;" alt="Logo">
        @endif
    </div>

    <div class="separator-double"></div>

    <div class="text-center" style="{{ $cssCajaTipo }} padding: 4px 0;">
        COMPROBANTE DE PAGO
    </div>

    <div class="separator"></div>

    <div style="padding: 2px 0 4px;">
        <table>
            <tr>
                <td style="{{ $cssInfoLabel }} width: 35%; padding: {{ $padPx - 2 }}px 0;">Fecha Pago:</td>
                <td style="{{ $cssInfoValor }} padding: {{ $padPx - 2 }}px 0;">{{ \Carbon\Carbon::parse($pago->fecha)->format('d/m/Y') }}</td>
            </tr>
            <tr>
                <td style="{{ $cssInfoLabel }} padding: {{ $padPx - 2 }}px 0;">M&eacute;todo Pago:</td>
                <td style="{{ $cssInfoValor }} padding: {{ $padPx - 2 }}px 0;">{{ $metodoPago }}</td>
            </tr>
            @if($pago->numero_operacion)
            <tr>
                <td style="{{ $cssInfoLabel }} padding: {{ $padPx - 2 }}px 0;">N&deg; Operaci&oacute;n:</td>
                <td style="{{ $cssInfoValor }} padding: {{ $padPx - 2 }}px 0;">{{ $pago->numero_operacion }}</td>
            </tr>
            @endif
            @if($pago->numero_letra)
            <tr>
                <td style="{{ $cssInfoLabel }} padding: {{ $padPx - 2 }}px 0;">N&deg; Letra:</td>
                <td style="{{ $cssInfoValor }} padding: {{ $padPx - 2 }}px 0;">{{ $pago->numero_letra }}</td>
            </tr>
            @endif
        </table>
    </div>

    <div class="separator"></div>

    <div class="text-center" style="padding: 6px 0;">
        <div style="{{ $cssInfoLabel }} margin-bottom: 2px;">MONTO PAGADO</div>
        <div style="{{ $cssCajaNumero }} font-size: 14pt; font-weight: bold;">S/. {{ number_format($pago->monto, 2) }}</div>
    </div>

    <div class="separator"></div>

    <div style="padding: 2px 0 4px;">
        <div class="text-center" style="{{ $cssInfoLabel }} margin-bottom: 3px;">REFERENCIA DE COMPRA</div>
        <table>
            <tr>
                <td style="{{ $cssInfoLabel }} width: 35%; padding: {{ $padPx - 2 }}px 0;">Documento:</td>
                <td style="{{ $cssInfoValor }} padding: {{ $padPx - 2 }}px 0;">{{ $tipoDoc }} {{ $nroDocumento }}</td>
            </tr>
            <tr>
                <td style="{{ $cssInfoLabel }} padding: {{ $padPx - 2 }}px 0;">Proveedor:</td>
                <td style="{{ $cssInfoValor }} padding: {{ $padPx - 2 }}px 0;">{{ $proveedorNombre }}</td>
            </tr>
            <tr>
                <td style="{{ $cssInfoLabel }} padding: {{ $padPx - 2 }}px 0;">RUC:</td>
                <td style="{{ $cssInfoValor }} padding: {{ $padPx - 2 }}px 0;">{{ $proveedorRuc }}</td>
            </tr>
        </table>
    </div>

    <div class="separator"></div>

    <div style="padding: 2px 0 4px;">
        <table>
            <tr>
                <td style="{{ $cssInfoLabel }} width: 55%; padding: {{ $padPx - 2 }}px 0;">Total de Compra:</td>
                <td style="{{ $cssInfoValor }} text-align: right; padding: {{ $padPx - 2 }}px 0;">S/. {{ $totalNeto }}</td>
            </tr>
            <tr>
                <td style="{{ $cssInfoLabel }} padding: {{ $padPx - 2 }}px 0;">Total Pagado:</td>
                <td style="{{ $cssInfoValor }} text-align: right; padding: {{ $padPx - 2 }}px 0;">S/. {{ $totalPagado }}</td>
            </tr>
            <tr>
                <td style="{{ $cssTotalLabel }} padding-top: 2px;">SALDO PENDIENTE:</td>
                <td style="{{ $cssTotalValor }} text-align: right; padding-top: 2px;">S/. {{ $saldoPendiente }}</td>
            </tr>
        </table>
    </div>

    <div class="separator-double"></div>

    @if($pago->observacion)
    <div style="padding: 2px 0;">
        <span style="{{ $cssInfoLabel }}">Obs:</span>
        <span style="{{ $cssInfoValor }}">{{ $pago->observacion }}</span>
    </div>
    <div class="separator"></div>
    @endif

    <div class="text-center" style="{{ $cssDespedida }} padding: 4px 0;">
        Comprobante de pago generado el {{ now()->format('d/m/Y H:i') }}
    </div>
</body>
</html>
