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
        .bg-gray { background-color: #f0f0f0; }
        .bg-green { background-color: #e8f5e9; }
        .bg-blue { background-color: #e3f2fd; }
    </style>
</head>
<body>
    {{-- Header: Logo + Empresa (mismo estilo que venta-ticket) --}}
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

    {{-- Tipo documento y número --}}
    <div class="text-center text-bold" style="font-size: 9pt; padding: 4px 0;">
        APERTURA DE CAJA ELECTRÓNICA<br>
        {{ $nroDoc }}
    </div>

    <div class="separator"></div>

    {{-- Info de la apertura --}}
    <div style="padding: 2px 0 6px;">
        <table>
            <tr>
                <td class="label" style="width: 25%;">FECHA:</td>
                <td class="value">{{ \App\Services\Pdf\PdfService::formatFecha($apertura->fecha_apertura) }}</td>
                <td class="label" style="width: 25%;">HORA:</td>
                <td class="value">{{ \App\Services\Pdf\PdfService::formatFecha($apertura->fecha_apertura, 'H:i') }}</td>
            </tr>
            <tr>
                <td class="label">CAJA:</td>
                <td class="value" colspan="3">{{ $apertura->cajaPrincipal->name ?? $apertura->cajaPrincipal->nombre ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">USUARIO:</td>
                <td class="value" colspan="3">{{ $apertura->user->name ?? '-' }}</td>
            </tr>
        </table>
    </div>

    <div class="separator"></div>

    {{-- Monto de Apertura --}}
    <div class="section-title">MONTO DE APERTURA</div>
    <table style="font-size: 6pt;">
        <tr class="bg-green">
            <td style="padding: 3px; font-size: 7pt;" class="text-bold">Total Efectivo:</td>
            <td style="padding: 3px; font-size: 7pt; color: green;" class="text-right text-bold">S/ {{ number_format($apertura->monto_apertura, 2) }}</td>
        </tr>
    </table>

    {{-- Desglose de Denominaciones --}}
    @if($conteo)
        @php
            $denominaciones = [
                ['label' => 'Billete S/. 200', 'valor' => 200, 'key' => 'b200'],
                ['label' => 'Billete S/. 100', 'valor' => 100, 'key' => 'b100'],
                ['label' => 'Billete S/. 50', 'valor' => 50, 'key' => 'b50'],
                ['label' => 'Billete S/. 20', 'valor' => 20, 'key' => 'b20'],
                ['label' => 'Billete S/. 10', 'valor' => 10, 'key' => 'b10'],
                ['label' => 'Moneda S/. 5', 'valor' => 5, 'key' => 'm5'],
                ['label' => 'Moneda S/. 2', 'valor' => 2, 'key' => 'm2'],
                ['label' => 'Moneda S/. 1', 'valor' => 1, 'key' => 'm1'],
                ['label' => 'Moneda S/. 0.50', 'valor' => 0.5, 'key' => 'm050'],
                ['label' => 'Moneda S/. 0.20', 'valor' => 0.2, 'key' => 'm020'],
                ['label' => 'Moneda S/. 0.10', 'valor' => 0.1, 'key' => 'm010'],
                ['label' => 'Moneda S/. 0.05', 'valor' => 0.05, 'key' => 'm005'],
            ];
            $denomsConValor = array_filter($denominaciones, fn($d) => ($conteo[$d['key']] ?? 0) > 0);
        @endphp
        @if(count($denomsConValor) > 0)
        <div class="section-title">DESGLOSE DE DENOMINACIONES</div>
        <table style="font-size: 6pt;">
            <tr style="border-bottom: 1px solid #000;">
                <td class="text-bold">Denominación</td>
                <td class="text-bold text-center" style="width: 30px;">Cant.</td>
                <td class="text-bold text-right" style="width: 45px;">Total</td>
            </tr>
            @foreach($denomsConValor as $i => $denom)
                @php
                    $cantidad = $conteo[$denom['key']] ?? 0;
                    $subtotalDenom = $cantidad * $denom['valor'];
                @endphp
                <tr style="background-color: {{ $i % 2 === 0 ? '#fff' : '#f9f9f9' }};">
                    <td>{{ $denom['label'] }}</td>
                    <td class="text-center">{{ $cantidad }}</td>
                    <td class="text-right">S/ {{ number_format($subtotalDenom, 2) }}</td>
                </tr>
            @endforeach
            <tr class="bg-gray" style="border-top: 1px solid #000;">
                <td class="text-bold">Total</td>
                <td></td>
                <td class="text-right text-bold">S/ {{ number_format($apertura->monto_apertura, 2) }}</td>
            </tr>
        </table>
        @endif
    @endif

    {{-- Distribución a Vendedores --}}
    @if(count($distribuciones) > 0)
    <div class="section-title">DISTRIBUCIÓN A VENDEDORES ({{ count($distribuciones) }})</div>
    <table style="font-size: 6pt;">
        <tr style="border-bottom: 1px solid #000;">
            <td class="text-bold">Vendedor</td>
            <td class="text-bold text-right" style="width: 50px;">Monto</td>
        </tr>
        @foreach($distribuciones as $i => $dist)
        <tr style="background-color: {{ $i % 2 === 0 ? '#fff' : '#f9f9f9' }};">
            <td>{{ $dist['vendedor'] }}</td>
            <td class="text-right">S/ {{ number_format($dist['monto'], 2) }}</td>
        </tr>
        @endforeach
        <tr class="bg-gray" style="border-top: 1px solid #000;">
            <td class="text-bold">TOTAL DISTRIBUIDO</td>
            <td class="text-right text-bold">S/ {{ number_format($apertura->monto_apertura, 2) }}</td>
        </tr>
    </table>
    @endif

    {{-- Información adicional --}}
    <div style="margin-top: 4px; padding-top: 4px; border-top: 1px dashed #000;">
        <div class="bg-blue" style="padding: 3px; text-align: center; font-size: 6pt; color: #1565c0;">
            Caja aperturada exitosamente
        </div>
    </div>

    {{-- Pie de página --}}
    <div style="margin-top: 6px; padding-top: 4px; border-top: 1px dashed #000;">
        <div style="text-align: center; font-size: 5pt; color: #666;">
            Gracias por usar nuestro sistema
        </div>
        <div style="text-align: center; font-size: 4pt; color: #999; margin-top: 2px;">
            Documento generado el {{ now()->format('d/m/Y H:i:s') }}
        </div>
    </div>
</body>
</html>
