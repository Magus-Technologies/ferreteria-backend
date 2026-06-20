<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Vale {{ $vale['codigo'] }}</title>
    @php
        $est = $est ?? [];
        $bloques = $bloques ?? [];
        $colorBorde = $est['color_borde'] ?? '#000';
        $colorTema = $est['color_tema'] ?? '#000';
        $borderThin = $est['border_thin_px'] ?? 1;
        $borderPx = $est['border_px'] ?? 2;
        $fuente = $est['fuente'] ?? 'Helvetica';
        $fontPt = $est['font_pt'] ?? 7;

        $cssEmpresaRazon = $bloques['empresa_razon']['css'] ?? '';
        $cssCajaTipo = $bloques['caja_tipo']['css'] ?? '';
        $cssCajaNumero = $bloques['caja_numero']['css'] ?? '';
        $cssInfoLabel = $bloques['info_label']['css'] ?? '';
        $cssInfoValor = $bloques['info_valor']['css'] ?? '';
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
        .text-bold { font-weight: bold; }
    </style>
</head>
<body>
    {{-- Header empresa --}}
    <div class="text-center" style="margin-bottom: 4px;">
        @if($logoPath && !($msg['ocultar_logo'] ?? false))
            <img src="{{ $logoPath }}" style="max-height: 80px; max-width: 140px;" alt="Logo">
        @endif
        <div style="margin-top: 2px;">
            <div style="{{ $cssEmpresaRazon }}">{{ $empresa->razon_social }}</div>
        </div>
    </div>

    {{-- Vale card --}}
    <div style="border: {{ $borderPx - 0.5 }}px solid {{ $colorBorde }}; border-radius: 4px; padding: 6px; margin-top: 4px;">
        <div class="text-center" style="{{ $cssCajaTipo }} background-color: {{ $colorTema }}; color: #fff; padding: 4px; margin-bottom: 4px; border-radius: 2px;">
            VALE DE COMPRA - {{ $vale['tipo_label'] }}
        </div>
        <div class="text-center text-bold" style="font-size: {{ $fontPt }}pt; margin-bottom: 3px;">
            {{ $vale['nombre'] }}
        </div>
        <div class="text-center text-bold" style="{{ $cssCajaNumero }} border: {{ $borderThin }}px solid {{ $colorBorde }}; padding: 3px; margin-bottom: 4px; border-radius: 2px;">
            {{ $vale['beneficio'] }}
        </div>

        @if($vale['codigo'])
        {{-- Codigo de texto --}}
        <div class="text-center" style="background-color: #f0f0f0; padding: 4px; margin-bottom: 4px; border-radius: 2px;">
            <div style="{{ $cssInfoLabel }}">C&Oacute;DIGO:</div>
            <div style="{{ $cssCajaNumero }} font-size: {{ $fontPt + 3 }}pt; letter-spacing: 1px;">{{ $vale['codigo'] }}</div>
        </div>

        {{-- Codigo de barras (Code 128) --}}
        @if(isset($barcodeBase64))
        <div class="text-center" style="margin-bottom: 4px;">
            <img src="{{ $barcodeBase64 }}" style="width: 90%; max-width: 60mm; height: auto;" alt="Barcode">
        </div>
        @endif

        {{-- QR Code --}}
        @if(isset($qrBase64))
        <div class="text-center" style="margin-bottom: 4px;">
            <img src="{{ $qrBase64 }}" style="width: 100px; height: 100px;" alt="QR">
            <div style="color: #666; font-size: {{ max(5, $fontPt - 2) }}pt;">Escanea para canjear</div>
        </div>
        @endif
        @endif

        @if($vale['fecha_validez'])
        <div class="text-center" style="{{ $cssInfoValor }} margin-bottom: 2px;">
            V&aacute;lido hasta: {{ $vale['fecha_validez'] }}
        </div>
        @endif
        <div class="text-center" style="color: #666; border-top: {{ $borderThin }}px dashed #999; padding-top: 3px; margin-top: 2px; font-size: {{ max(5, $fontPt - 2) }}pt;">
            Boleta: {{ $numeroDocumento }} | {{ $fechaEmision }}
        </div>
    </div>
</body>
</html>
