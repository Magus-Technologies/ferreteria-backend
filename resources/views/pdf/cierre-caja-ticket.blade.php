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
        .bg-red { background-color: #ffebee; }
        .bg-blue { background-color: #e3f2fd; }
        .bg-yellow { background-color: #fff3cd; }
        .color-green { color: green; }
        .color-red { color: red; }
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
        CIERRE DE CAJA ELECTRÓNICA<br>
        {{ $nroDoc }}
    </div>

    <div class="separator"></div>

    {{-- Info del cierre --}}
    <div style="padding: 2px 0 6px;">
        <table>
            <tr>
                <td class="label" style="width: 25%;">F. APERTURA:</td>
                <td class="value">{{ \App\Services\Pdf\PdfService::formatFecha($cierre->fecha_apertura) }}</td>
                <td class="label" style="width: 25%;">HORA:</td>
                <td class="value">{{ \App\Services\Pdf\PdfService::formatFecha($cierre->fecha_apertura, 'H:i') }}</td>
            </tr>
            @if($cierre->fecha_cierre)
            <tr>
                <td class="label">F. CIERRE:</td>
                <td class="value">{{ \App\Services\Pdf\PdfService::formatFecha($cierre->fecha_cierre) }}</td>
                <td class="label">HORA:</td>
                <td class="value">{{ \App\Services\Pdf\PdfService::formatFecha($cierre->fecha_cierre, 'H:i') }}</td>
            </tr>
            @endif
            <tr>
                <td class="label">CAJA:</td>
                <td class="value" colspan="3">{{ $cierre->cajaPrincipal->name ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">USUARIO:</td>
                <td class="value" colspan="3">{{ $cierre->user->name ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">SUPERVISOR:</td>
                <td class="value" colspan="3">{{ $cierre->supervisor->name ?? '-' }}</td>
            </tr>
        </table>
    </div>

    {{-- Resumen de Saldos --}}
    <div class="section-title">RESUMEN DE SALDOS</div>
    <table style="font-size: 6pt;">
        <tr>
            <td>Efectivo Inicial:</td>
            <td class="text-right text-bold">S/ {{ number_format($resumen['efectivo_inicial'], 2) }}</td>
        </tr>
    </table>

    {{-- Movimientos de Caja --}}
    <div class="section-title">MOVIMIENTOS DE CAJA</div>
    <table style="font-size: 6pt;">
        @if($otrosIngresos > 0)
        <tr>
            <td>Otros Ingresos:</td>
            <td class="text-right">S/ {{ number_format($otrosIngresos, 2) }}</td>
        </tr>
        @endif
        @if($resumen['total_prestamos_recibidos'] > 0)
        <tr>
            <td>Préstamos Recibidos ({{ count($resumen['prestamos_recibidos']) }}):</td>
            <td class="text-right">S/ {{ number_format($resumen['total_prestamos_recibidos'], 2) }}</td>
        </tr>
        @endif
        @if($gastos > 0)
        <tr>
            <td>Gastos:</td>
            <td class="text-right">S/ {{ number_format($gastos, 2) }}</td>
        </tr>
        @endif
        @if($resumen['total_prestamos_dados'] > 0)
        <tr>
            <td>Préstamos Dados ({{ count($resumen['prestamos_dados']) }}):</td>
            <td class="text-right">S/ {{ number_format($resumen['total_prestamos_dados'], 2) }}</td>
        </tr>
        @endif
    </table>

    {{-- Totales Generales --}}
    <div class="section-title">TOTALES GENERALES</div>
    <table style="font-size: 6pt;">
        <tr>
            <td class="text-bold">Resumen Ventas:</td>
            <td class="text-right text-bold">S/ {{ number_format($resumen['total_ventas'], 2) }}</td>
        </tr>
        <tr>
            <td>Resumen Ingresos:</td>
            <td class="text-right">S/ {{ number_format($resumen['total_ingresos'], 2) }}</td>
        </tr>
        <tr>
            <td>Resumen Egresos:</td>
            <td class="text-right">S/ {{ number_format($resumen['total_egresos'], 2) }}</td>
        </tr>
        <tr class="bg-gray">
            <td style="padding: 3px; font-size: 7pt;" class="text-bold">Total en Caja (Efectivo):</td>
            <td style="padding: 3px; font-size: 7pt;" class="text-right text-bold">S/ {{ number_format($montoEsperado, 2) }}</td>
        </tr>
    </table>

    {{-- Cierre Físico --}}
    <div class="section-title">CIERRE FÍSICO</div>
    @if($cierre->monto_cierre_efectivo !== null)
    <table style="font-size: 6pt;">
        <tr>
            <td>Dinero Efectivo:</td>
            <td class="text-right">S/ {{ number_format($montoCierre, 2) }}</td>
        </tr>
        <tr>
            <td>Total Cuentas:</td>
            <td class="text-right">S/ {{ number_format($totalCuentas, 2) }}</td>
        </tr>
        <tr class="bg-gray">
            <td style="padding: 3px; font-size: 7pt;" class="text-bold">Total Cierre Físico:</td>
            <td style="padding: 3px; font-size: 7pt;" class="text-right text-bold">S/ {{ number_format($montoCierre + $totalCuentas, 2) }}</td>
        </tr>
    </table>
    @else
    <div class="bg-yellow" style="padding: 3px; text-align: center; font-size: 6pt; color: #856404;">
        Pendiente de cierre
    </div>
    @endif

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
                <td class="text-right text-bold">S/ {{ number_format($montoCierre, 2) }}</td>
            </tr>
        </table>
        @endif
    @endif

    {{-- Diferencias --}}
    <div class="section-title">DIFERENCIAS</div>
    <table style="font-size: 6pt;">
        <tr class="bg-blue">
            <td style="padding: 2px 4px;" class="text-bold">Monto Esperado:</td>
            <td style="padding: 2px 4px;" class="text-right text-bold">S/ {{ number_format($montoEsperado, 2) }}</td>
        </tr>
        @if($cierre->monto_cierre_efectivo !== null)
        <tr>
            <td>Sobrante:</td>
            <td class="text-right color-green">S/ {{ number_format($sobrante, 2) }}</td>
        </tr>
        <tr>
            <td>Faltante:</td>
            <td class="text-right color-red">S/ {{ number_format($faltante, 2) }}</td>
        </tr>
        <tr style="background-color: {{ $diferencia < 0 ? '#ffebee' : '#e8f5e9' }};">
            <td style="padding: 3px; font-size: 7pt;" class="text-bold">Diferencia Total:</td>
            <td style="padding: 3px; font-size: 7pt;" class="text-right text-bold {{ $diferencia < 0 ? 'color-red' : 'color-green' }}">
                S/ {{ number_format($diferencia, 2) }}
            </td>
        </tr>
        @else
        <tr>
            <td colspan="2" class="bg-yellow" style="text-align: center; padding: 3px; color: #856404;">
                Pendiente de cierre
            </td>
        </tr>
        @endif
    </table>

    {{-- Observaciones --}}
    <div class="section-title">OBSERVACIONES</div>
    <div class="bg-yellow" style="padding: 3px; font-size: 6pt;">
        {{ $cierre->comentarios ?: '-' }}
    </div>

    {{-- Estado del Cierre --}}
    @if($cierre->monto_cierre_efectivo !== null)
    <div class="section-title">ESTADO DEL CIERRE</div>
    <div style="padding: 4px; text-align: center; font-size: 8pt; font-weight: bold; background-color: {{ $diferencia == 0 ? '#e8f5e9' : '#ffebee' }}; color: {{ $diferencia == 0 ? 'green' : 'red' }};">
        {{ $diferencia == 0 ? '✓ CAJA CUADRADA' : ($diferencia > 0 ? '⚠ SOBRANTE' : '⚠ FALTANTE') }}
    </div>
    @endif

    {{-- Métodos de Pago --}}
    <div class="section-title">MÉTODOS DE PAGO</div>
    <table style="font-size: 6pt;">
        <tr style="border-bottom: 1px solid #000;">
            <td class="text-bold">Método</td>
            <td class="text-bold text-center" style="width: 25px;">Cant.</td>
            <td class="text-bold text-right" style="width: 45px;">Total</td>
        </tr>
        @forelse($resumen['detalle_metodos_pago'] as $i => $metodo)
        <tr style="background-color: {{ $i % 2 === 0 ? '#fff' : '#f9f9f9' }};">
            <td>{{ $metodo['label'] }}</td>
            <td class="text-center">{{ $metodo['cantidad_transacciones'] ?? 0 }}</td>
            <td class="text-right">{{ number_format($metodo['total'] ?? 0, 2) }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="3" style="text-align: center; font-style: italic; color: #666;">Sin métodos de pago</td>
        </tr>
        @endforelse
    </table>

    {{-- Total Ventas --}}
    <table style="margin-top: 4px;">
        <tr style="border-top: 2px solid #000; background-color: #f0f0f0;">
            <td style="padding: 4px; font-size: 8pt; font-weight: bold;">TOTAL VENTAS</td>
            <td style="padding: 4px; font-size: 8pt; font-weight: bold; text-align: right;">S/ {{ number_format($resumen['total_ventas'], 2) }}</td>
        </tr>
    </table>
    <div style="text-align: center; font-size: 6pt; margin-top: 2px;">
        {{ \App\Services\Pdf\PdfService::numeroALetras($resumen['total_ventas']) }} SOLES
    </div>
</body>
</html>
