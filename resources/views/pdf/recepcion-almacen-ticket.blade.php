<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $nroDoc }}</title>
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
        table { border-collapse: collapse; width: 100%; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-bold { font-weight: bold; }
        .separator { border-top: 1px dashed #000; margin: 4px 0; }
        .section-title {
            font-size: 7pt;
            font-weight: bold;
            text-align: center;
            margin-bottom: 2px;
            padding-top: 4px;
            border-top: 1px dashed #000;
        }
        .label { font-weight: bold; text-transform: uppercase; font-size: 5pt; }
        .value { font-size: 5pt; }
    </style>
</head>
<body>
    {{-- Header --}}
    <div class="text-center" style="margin-bottom: 4px;">
        @if(!empty($logoPath))
            <img src="{{ $logoPath }}" style="max-height: 120px; max-width: 180px;" alt="Logo">
        @endif
        <div style="margin-top: 2px;">
            <div class="text-bold" style="font-size: 9pt;">{{ $empresa->razon_social }}</div>
            <div class="text-bold">R.U.C. {{ $empresa->ruc }}</div>
            <div>{{ $empresa->direccion }}</div>
            @if($empresa->telefono ?? $empresa->celular ?? null)
                <div><span class="text-bold">Cel:</span> {{ $empresa->telefono ?? $empresa->celular }}</div>
            @endif
            @if($empresa->email ?? null)
                <div><span class="text-bold">Email:</span> {{ $empresa->email }}</div>
            @endif
        </div>
    </div>

    <div class="separator"></div>

    <div class="text-center text-bold" style="font-size: 9pt; padding: 4px 0;">
        RECEPCIÓN DE ALMACÉN ELECTRÓNICA<br>
        {{ $nroDoc }}
    </div>

    <div class="separator"></div>

    {{-- Info --}}
    <div style="padding: 2px 0 6px;">
        <table>
            <tr>
                <td class="label" style="width: 30%;">F. RECEPCIÓN:</td>
                <td class="value">{{ \App\Services\Pdf\PdfService::formatFecha($recepcion->fecha) }}</td>
            </tr>
            <tr>
                <td class="label">ALMACÉN:</td>
                <td class="value">{{ $src->almacen->name ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">F. COMPRA:</td>
                <td class="value">{{ \App\Services\Pdf\PdfService::formatFecha($src->fecha ?? null) }}</td>
            </tr>
            <tr>
                <td class="label">USUARIO:</td>
                <td class="value">{{ $recepcion->user->name ?? '-' }}</td>
            </tr>
        </table>
    </div>

    {{-- Proveedor --}}
    <div class="section-title">DATOS DEL PROVEEDOR</div>
    <table style="font-size: 6pt;">
        <tr>
            <td class="label" style="width: 30%;">RUC:</td>
            <td class="value">{{ $src->proveedor->ruc ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">RAZÓN SOCIAL:</td>
            <td class="value">{{ $src->proveedor->razon_social ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">DOCUMENTO:</td>
            <td class="value">
                @if($recepcion->compra)
                    {{ ($recepcion->compra->serie ?? '') . '-' . ($recepcion->compra->numero ?? '') }}
                @elseif($recepcion->ordenCompra)
                    {{ $recepcion->ordenCompra->codigo ?? '-' }}
                @else
                    -
                @endif
            </td>
        </tr>
        <tr>
            <td class="label">GUÍA REMISIÓN:</td>
            <td class="value">{{ $src->guia ?? '-' }}</td>
        </tr>
    </table>

    {{-- Transportista --}}
    <div class="section-title">DATOS DEL TRANSPORTISTA</div>
    <table style="font-size: 6pt;">
        <tr>
            <td class="label" style="width: 30%;">RUC:</td>
            <td class="value">{{ $recepcion->transportista_ruc ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">RAZÓN SOCIAL:</td>
            <td class="value">{{ $recepcion->transportista_razon_social ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">PLACA:</td>
            <td class="value">{{ $recepcion->transportista_placa ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">LICENCIA:</td>
            <td class="value">{{ $recepcion->transportista_licencia ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">NOMBRES:</td>
            <td class="value">{{ $recepcion->transportista_name ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">GUÍA REM. TRANSP.:</td>
            <td class="value">{{ $recepcion->transportista_guia_remision ?? '-' }}</td>
        </tr>
    </table>

    {{-- Tabla productos --}}
    <div class="section-title">PRODUCTOS</div>
    <table style="font-size: 5pt;">
        <tr style="border-bottom: 1px solid #000;">
            <td class="text-bold" style="width: 35px;">Cód.</td>
            <td class="text-bold">Producto</td>
            <td class="text-bold text-center" style="width: 40px;">Unidad</td>
            <td class="text-bold text-center" style="width: 30px;">Cant.</td>
        </tr>
        @foreach($productos as $i => $p)
        <tr style="background-color: {{ $i % 2 === 0 ? '#fff' : '#f9f9f9' }};">
            <td>{{ $p['codigo'] }}</td>
            <td>{{ $p['bonificacion'] ? '* ' : '' }}{{ $p['nombre'] }}</td>
            <td class="text-center">{{ $p['unidad'] }}</td>
            <td class="text-center">{{ number_format($p['cantidad'], 0) }}</td>
        </tr>
        @endforeach
    </table>

    {{-- Total --}}
    <table style="margin-top: 4px;">
        <tr style="border-top: 2px solid #000; background-color: #f0f0f0;">
            <td style="padding: 4px; font-size: 8pt; font-weight: bold;">TOTAL ITEMS</td>
            <td style="padding: 4px; font-size: 8pt; font-weight: bold; text-align: right;">{{ number_format($total, 0) }}</td>
        </tr>
    </table>

    {{-- Observaciones --}}
    @if($recepcion->observaciones)
    <div class="section-title">OBSERVACIONES</div>
    <div style="padding: 3px; font-size: 6pt;">
        {{ $recepcion->observaciones }}
    </div>
    @endif

    {{-- Footer --}}
    <div style="margin-top: 6px; padding-top: 4px; border-top: 1px dashed #000;">
        <div style="text-align: center; font-size: 4pt; color: #999;">
            Documento generado el {{ now()->format('d/m/Y H:i:s') }}
        </div>
    </div>
</body>
</html>
