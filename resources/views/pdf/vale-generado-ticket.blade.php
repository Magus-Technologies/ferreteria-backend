<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Vale {{ $vale['codigo'] }}</title>
    <style>
        @page {
            size: 80mm auto;
            margin: 3mm;
        }
        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 7pt;
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
        @if($logoPath)
            <img src="{{ $logoPath }}" style="max-height: 80px; max-width: 140px;" alt="Logo">
        @endif
        <div style="margin-top: 2px;">
            <div class="text-bold" style="font-size: 8pt;">{{ $empresa->razon_social }}</div>
        </div>
    </div>

    {{-- Vale card --}}
    <div style="border: 1.5px solid #000; border-radius: 4px; padding: 6px; margin-top: 4px;">
        <div class="text-center text-bold" style="background-color: #000; color: #fff; font-size: 8pt; padding: 4px; margin-bottom: 4px; border-radius: 2px;">
            VALE DE COMPRA - {{ $vale['tipo_label'] }}
        </div>
        <div class="text-center text-bold" style="font-size: 7pt; margin-bottom: 3px;">
            {{ $vale['nombre'] }}
        </div>
        <div class="text-center text-bold" style="font-size: 9pt; border: 1px solid #000; padding: 3px; margin-bottom: 4px; border-radius: 2px;">
            {{ $vale['beneficio'] }}
        </div>

        @if($vale['codigo'])
        {{-- Codigo de texto --}}
        <div class="text-center" style="background-color: #f0f0f0; padding: 4px; margin-bottom: 4px; border-radius: 2px;">
            <div style="font-size: 6pt;">C&Oacute;DIGO:</div>
            <div class="text-bold" style="font-size: 10pt; letter-spacing: 1px;">{{ $vale['codigo'] }}</div>
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
            <div style="font-size: 5pt; color: #666;">Escanea para canjear</div>
        </div>
        @endif
        @endif

        @if($vale['fecha_validez'])
        <div class="text-center" style="font-size: 6pt; margin-bottom: 2px;">
            V&aacute;lido hasta: {{ $vale['fecha_validez'] }}
        </div>
        @endif
        <div class="text-center" style="font-size: 5pt; color: #666; border-top: 1px dashed #999; padding-top: 3px; margin-top: 2px;">
            Boleta: {{ $numeroDocumento }} | {{ $fechaEmision }}
        </div>
    </div>
</body>
</html>
