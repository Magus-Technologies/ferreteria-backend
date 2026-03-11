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
        {{ $tipoDoc }}<br>
        {{ $nroDoc }}
    </div>

    <div class="separator"></div>

    {{-- Info --}}
    <div style="padding: 2px 0 6px;">
        <table>
            <tr>
                <td class="label" style="width: 30%;">F. EMISIÓN:</td>
                <td class="value">{{ \App\Services\Pdf\PdfService::formatFecha($ingreso->fecha) }}</td>
            </tr>
            <tr>
                <td class="label">ALMACÉN:</td>
                <td class="value">{{ $ingreso->almacen->name ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">USUARIO:</td>
                <td class="value">{{ $ingreso->user->name ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">PROVEEDOR:</td>
                <td class="value">{{ $ingreso->proveedor->razon_social ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">TIPO:</td>
                <td class="value">{{ $ingreso->tipoIngreso->name ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">OBSERVACIONES:</td>
                <td class="value">{{ $ingreso->descripcion ?? '-' }}</td>
            </tr>
        </table>
    </div>

    {{-- Tabla productos --}}
    <div class="section-title">PRODUCTOS</div>
    <table style="font-size: 5pt;">
        <tr style="border-bottom: 1px solid #000;">
            <td class="text-bold" style="width: 35px;">Cód.</td>
            <td class="text-bold" style="width: 30px;">Cant.</td>
            <td class="text-bold">Producto</td>
            <td class="text-bold text-center" style="width: 35px;">Stk Ant.</td>
            <td class="text-bold text-center" style="width: 35px;">Stk Nvo.</td>
            <td class="text-bold text-right" style="width: 35px;">Costo</td>
        </tr>
        @foreach($productos as $i => $p)
        <tr style="background-color: {{ $i % 2 === 0 ? '#fff' : '#f9f9f9' }};">
            <td>{{ $p['codigo'] }}</td>
            <td>{{ number_format($p['cantidad'], 0) }}</td>
            <td>{{ $p['nombre'] }}</td>
            <td class="text-center">{{ number_format($p['stock_anterior'], 0) }}</td>
            <td class="text-center">{{ number_format($p['stock_nuevo'], 0) }}</td>
            <td class="text-right">{{ number_format($p['costo'], 2) }}</td>
        </tr>
        @endforeach
    </table>

    {{-- Total --}}
    <table style="margin-top: 4px;">
        <tr style="border-top: 2px solid #000; background-color: #f0f0f0;">
            <td style="padding: 4px; font-size: 8pt; font-weight: bold;">TOTAL</td>
            <td style="padding: 4px; font-size: 8pt; font-weight: bold; text-align: right;">S/ {{ number_format($total, 2) }}</td>
        </tr>
    </table>
    <div style="text-align: center; font-size: 6pt; margin-top: 2px;">
        {{ $son }} SOLES
    </div>

    {{-- Footer --}}
    <div style="margin-top: 6px; padding-top: 4px; border-top: 1px dashed #000;">
        <div style="text-align: center; font-size: 4pt; color: #999;">
            Documento generado el {{ now()->format('d/m/Y H:i:s') }}
        </div>
    </div>
</body>
</html>
