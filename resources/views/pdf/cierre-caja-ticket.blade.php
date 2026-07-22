<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $nroDoc }}</title>
    @php
        $est = $est ?? [];
        $bloques = $bloques ?? [];
        $colorBorde = $est['color_borde'] ?? '#000';
        $borderThin = $est['border_thin_px'] ?? 1;
        $borderPx = $est['border_px'] ?? 2;
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
        table { border-collapse: collapse; width: 100%; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .separator { border-top: {{ $borderThin }}px dashed {{ $colorBorde }}; margin: 4px 0; }
        .section-title {
            text-align: center;
            margin-bottom: 2px;
            padding-top: 4px;
            border-top: {{ $borderThin }}px dashed {{ $colorBorde }};
        }
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
    {{-- Header: Logo + Empresa --}}
    <div class="text-center" style="margin-bottom: 4px;">
        @if(!empty($logoPath) && !($msg['ocultar_logo'] ?? false))
            <img src="{{ $logoPath }}" style="max-height: 120px; max-width: 180px;" alt="Logo">
        @endif
        <div style="margin-top: 2px;">
            <div style="{{ $cssEmpresaRazon }}">{{ $empresa->razon_social }}</div>
            <div style="{{ $cssCajaRuc }}">R.U.C. {{ $empresa->ruc }}</div>
            <div style="{{ $cssEmpresaDir }}">{{ $empresa->direccion }}</div>
            @if($empresa->telefono ?? $empresa->celular ?? null)
                <div style="{{ $cssEmpresaDir }}"><strong>Cel:</strong> {{ $empresa->telefono ?? $empresa->celular }}</div>
            @endif
            @if($empresa->email ?? null)
                <div style="{{ $cssEmpresaDir }}"><strong>Email:</strong> {{ $empresa->email }}</div>
            @endif
        </div>
    </div>

    <div class="separator"></div>

    {{-- Tipo documento y número --}}
    <div class="text-center" style="padding: 4px 0;">
        <div style="{{ $cssCajaTipo }}">CIERRE DE CAJA ELECTRÓNICA</div>
        <div style="{{ $cssCajaNumero }}">{{ $nroDoc }}</div>
    </div>

    <div class="separator"></div>

    {{-- Info del cierre --}}
    <div style="padding: 2px 0 6px;">
        <table>
            <tr>
                <td style="{{ $cssInfoLabel }} width: 25%;">F. APERTURA:</td>
                <td style="{{ $cssInfoValor }}">{{ \App\Services\Pdf\PdfService::formatFecha($cierre->fecha_apertura) }}</td>
                <td style="{{ $cssInfoLabel }} width: 25%;">HORA:</td>
                <td style="{{ $cssInfoValor }}">{{ \App\Services\Pdf\PdfService::formatFecha($cierre->fecha_apertura, 'H:i') }}</td>
            </tr>
            @if($cierre->fecha_cierre)
            <tr>
                <td style="{{ $cssInfoLabel }}">F. CIERRE:</td>
                <td style="{{ $cssInfoValor }}">{{ \App\Services\Pdf\PdfService::formatFecha($cierre->fecha_cierre) }}</td>
                <td style="{{ $cssInfoLabel }}">HORA:</td>
                <td style="{{ $cssInfoValor }}">{{ \App\Services\Pdf\PdfService::formatFecha($cierre->fecha_cierre, 'H:i') }}</td>
            </tr>
            @endif
            <tr>
                <td style="{{ $cssInfoLabel }}">CAJA:</td>
                <td style="{{ $cssInfoValor }}" colspan="3">{{ $cierre->cajaPrincipal->name ?? '-' }}</td>
            </tr>
            <tr>
                <td style="{{ $cssInfoLabel }}">USUARIO:</td>
                <td style="{{ $cssInfoValor }}" colspan="3">{{ $cierre->user->name ?? '-' }}</td>
            </tr>
            <tr>
                <td style="{{ $cssInfoLabel }}">SUPERVISOR:</td>
                <td style="{{ $cssInfoValor }}" colspan="3">{{ $cierre->supervisor->name ?? '-' }}</td>
            </tr>
        </table>
    </div>

    {{-- Resumen de Saldos --}}
    <div class="section-title" style="{{ $cssInfoLabel }}">RESUMEN DE SALDOS</div>
    <table>
        <tr>
            <td style="{{ $cssTablaFila }}">Efectivo Inicial:</td>
            <td style="{{ $cssTotalValor }} text-align: right;">S/ {{ number_format($resumen['efectivo_inicial'], 2) }}</td>
        </tr>
    </table>

    {{-- Movimientos de Caja --}}
    <div class="section-title" style="{{ $cssInfoLabel }}">MOVIMIENTOS DE CAJA</div>
    <table>
        @if($otrosIngresos > 0)
        <tr>
            <td style="{{ $cssTablaFila }}">Otros Ingresos:</td>
            <td style="{{ $cssTablaFila }} text-align: right;">S/ {{ number_format($otrosIngresos, 2) }}</td>
        </tr>
        @endif
        @if($resumen['total_prestamos_recibidos'] > 0)
        <tr>
            <td style="{{ $cssTablaFila }}">Préstamos Recibidos ({{ count($resumen['prestamos_recibidos']) }}):</td>
            <td style="{{ $cssTablaFila }} text-align: right;">S/ {{ number_format($resumen['total_prestamos_recibidos'], 2) }}</td>
        </tr>
        @endif
        @if($gastos > 0)
        <tr>
            <td style="{{ $cssTablaFila }}">Gastos:</td>
            <td style="{{ $cssTablaFila }} text-align: right;">S/ {{ number_format($gastos, 2) }}</td>
        </tr>
        @endif
        @if($resumen['total_prestamos_dados'] > 0)
        <tr>
            <td style="{{ $cssTablaFila }}">Préstamos Dados ({{ count($resumen['prestamos_dados']) }}):</td>
            <td style="{{ $cssTablaFila }} text-align: right;">S/ {{ number_format($resumen['total_prestamos_dados'], 2) }}</td>
        </tr>
        @endif
    </table>

    {{-- Traslados a Bóveda (informativo: no afecta los totales) --}}
    <div class="section-title" style="{{ $cssInfoLabel }}">TRASLADOS A BÓVEDA</div>
    <table>
        <tr style="border-bottom: {{ $borderThin }}px solid {{ $colorBorde }};">
            <td style="{{ $cssTablaHeader }}">Fecha</td>
            <td style="{{ $cssTablaHeader }}">Vendedor</td>
            <td style="{{ $cssTablaHeader }} text-align: right; width: 50px;">Monto</td>
        </tr>
        @forelse($trasladosBoveda ?? [] as $i => $traslado)
        <tr style="background-color: {{ $i % 2 === 0 ? '#fff' : '#f9f9f9' }};">
            <td style="{{ $cssTablaFila }}">{{ \App\Services\Pdf\PdfService::formatFecha($traslado->fecha_traslado, 'd/m H:i') }}</td>
            <td style="{{ $cssTablaFila }}">{{ $traslado->vendedor->name ?? '-' }}</td>
            <td style="{{ $cssTablaFila }} text-align: right;">S/ {{ number_format($traslado->monto, 2) }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="3" style="text-align: center; font-style: italic; color: #666;">Sin traslados a bóveda</td>
        </tr>
        @endforelse
        @if(count($trasladosBoveda ?? []) > 0)
        <tr class="bg-gray" style="border-top: {{ $borderThin }}px solid {{ $colorBorde }};">
            <td style="{{ $cssTotalLabel }}" colspan="2">Total Trasladado:</td>
            <td style="{{ $cssTotalValor }} text-align: right;">S/ {{ number_format($totalTrasladosBoveda ?? 0, 2) }}</td>
        </tr>
        @endif
    </table>

    {{-- Totales Generales --}}
    <div class="section-title" style="{{ $cssInfoLabel }}">TOTALES GENERALES</div>
    <table>
        <tr>
            <td style="{{ $cssTotalLabel }}">Resumen Ventas:</td>
            <td style="{{ $cssTotalValor }} text-align: right;">S/ {{ number_format($resumen['total_ventas'], 2) }}</td>
        </tr>
        <tr>
            <td style="{{ $cssTablaFila }}">Resumen Ingresos:</td>
            <td style="{{ $cssTablaFila }} text-align: right;">S/ {{ number_format($resumen['total_ingresos'], 2) }}</td>
        </tr>
        <tr>
            <td style="{{ $cssTablaFila }}">Resumen Egresos:</td>
            <td style="{{ $cssTablaFila }} text-align: right;">S/ {{ number_format($resumen['total_egresos'], 2) }}</td>
        </tr>
        <tr class="bg-gray">
            <td style="{{ $cssTotalLabel }} padding: 3px;">Total en Caja (Efectivo):</td>
            <td style="{{ $cssTotalValor }} padding: 3px; text-align: right;">S/ {{ number_format($montoEsperado, 2) }}</td>
        </tr>
    </table>

    {{-- Cierre Físico --}}
    <div class="section-title" style="{{ $cssInfoLabel }}">CIERRE FÍSICO</div>
    @if($cierre->monto_cierre_efectivo !== null)
    <table>
        <tr>
            <td style="{{ $cssTablaFila }}">Dinero Efectivo:</td>
            <td style="{{ $cssTablaFila }} text-align: right;">S/ {{ number_format($montoCierre, 2) }}</td>
        </tr>
        <tr>
            <td style="{{ $cssTablaFila }}">Total Cuentas:</td>
            <td style="{{ $cssTablaFila }} text-align: right;">S/ {{ number_format($totalCuentas, 2) }}</td>
        </tr>
        <tr class="bg-gray">
            <td style="{{ $cssTotalLabel }} padding: 3px;">Total Cierre Físico:</td>
            <td style="{{ $cssTotalValor }} padding: 3px; text-align: right;">S/ {{ number_format($montoCierre + $totalCuentas, 2) }}</td>
        </tr>
    </table>
    @else
    <div class="bg-yellow" style="padding: 3px; text-align: center; color: #856404;">
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
        <div class="section-title" style="{{ $cssInfoLabel }}">DESGLOSE DE DENOMINACIONES</div>
        <table>
            <tr style="border-bottom: {{ $borderThin }}px solid {{ $colorBorde }};">
                <td style="{{ $cssTablaHeader }}">Denominación</td>
                <td style="{{ $cssTablaHeader }} text-align: center; width: 30px;">Cant.</td>
                <td style="{{ $cssTablaHeader }} text-align: right; width: 45px;">Total</td>
            </tr>
            @foreach($denomsConValor as $i => $denom)
                @php
                    $cantidad = $conteo[$denom['key']] ?? 0;
                    $subtotalDenom = $cantidad * $denom['valor'];
                @endphp
                <tr style="background-color: {{ $i % 2 === 0 ? '#fff' : '#f9f9f9' }};">
                    <td style="{{ $cssTablaFila }}">{{ $denom['label'] }}</td>
                    <td style="{{ $cssTablaFila }} text-align: center;">{{ $cantidad }}</td>
                    <td style="{{ $cssTablaFila }} text-align: right;">S/ {{ number_format($subtotalDenom, 2) }}</td>
                </tr>
            @endforeach
            <tr class="bg-gray" style="border-top: {{ $borderThin }}px solid {{ $colorBorde }};">
                <td style="{{ $cssTotalLabel }}">Total</td>
                <td></td>
                <td style="{{ $cssTotalValor }} text-align: right;">S/ {{ number_format($montoCierre, 2) }}</td>
            </tr>
        </table>
        @endif
    @endif

    {{-- Diferencias --}}
    <div class="section-title" style="{{ $cssInfoLabel }}">DIFERENCIAS</div>
    <table>
        <tr class="bg-blue">
            <td style="{{ $cssTotalLabel }} padding: 2px 4px;">Monto Esperado:</td>
            <td style="{{ $cssTotalValor }} padding: 2px 4px; text-align: right;">S/ {{ number_format($montoEsperado, 2) }}</td>
        </tr>
        @if($cierre->monto_cierre_efectivo !== null)
        <tr>
            <td style="{{ $cssTablaFila }}">Sobrante:</td>
            <td style="{{ $cssTablaFila }} text-align: right;" class="color-green">S/ {{ number_format($sobrante, 2) }}</td>
        </tr>
        <tr>
            <td style="{{ $cssTablaFila }}">Faltante:</td>
            <td style="{{ $cssTablaFila }} text-align: right;" class="color-red">S/ {{ number_format($faltante, 2) }}</td>
        </tr>
        <tr style="background-color: {{ $diferencia < 0 ? '#ffebee' : '#e8f5e9' }};">
            <td style="{{ $cssTotalLabel }} padding: 3px;">Diferencia Total:</td>
            <td style="{{ $cssTotalValor }} padding: 3px; text-align: right;" class="{{ $diferencia < 0 ? 'color-red' : 'color-green' }}">
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
    <div class="section-title" style="{{ $cssInfoLabel }}">OBSERVACIONES</div>
    <div class="bg-yellow" style="padding: 3px;">
        {{ $cierre->comentarios ?: '-' }}
    </div>

    {{-- Estado del Cierre --}}
    @if($cierre->monto_cierre_efectivo !== null)
    <div class="section-title" style="{{ $cssInfoLabel }}">ESTADO DEL CIERRE</div>
    <div style="padding: 4px; text-align: center; font-weight: bold; background-color: {{ $diferencia == 0 ? '#e8f5e9' : '#ffebee' }}; color: {{ $diferencia == 0 ? 'green' : 'red' }}; font-size: {{ $fontPt + 1 }}pt;">
        {{ $diferencia == 0 ? '✓ CAJA CUADRADA' : ($diferencia > 0 ? '⚠ SOBRANTE' : '⚠ FALTANTE') }}
    </div>
    @endif

    {{-- Métodos de Pago --}}
    <div class="section-title" style="{{ $cssInfoLabel }}">MÉTODOS DE PAGO</div>
    <table>
        <tr style="border-bottom: {{ $borderThin }}px solid {{ $colorBorde }};">
            <td style="{{ $cssTablaHeader }}">Método</td>
            <td style="{{ $cssTablaHeader }} text-align: center; width: 25px;">Cant.</td>
            <td style="{{ $cssTablaHeader }} text-align: right; width: 45px;">Total</td>
        </tr>
        @forelse($resumen['detalle_metodos_pago'] as $i => $metodo)
        <tr style="background-color: {{ $i % 2 === 0 ? '#fff' : '#f9f9f9' }};">
            <td style="{{ $cssTablaFila }}">{{ $metodo['label'] }}</td>
            <td style="{{ $cssTablaFila }} text-align: center;">{{ $metodo['cantidad_transacciones'] ?? 0 }}</td>
            <td style="{{ $cssTablaFila }} text-align: right;">{{ number_format($metodo['total'] ?? 0, 2) }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="3" style="text-align: center; font-style: italic; color: #666;">Sin métodos de pago</td>
        </tr>
        @endforelse
    </table>

    {{-- Total Ventas --}}
    <table style="margin-top: 4px;">
        <tr style="border-top: {{ $borderPx }}px solid {{ $colorBorde }}; background-color: #f0f0f0;">
            <td style="{{ $cssTotalLabel }} padding: 4px;">TOTAL VENTAS</td>
            <td style="{{ $cssTotalValor }} padding: 4px; text-align: right;">S/ {{ number_format($resumen['total_ventas'], 2) }}</td>
        </tr>
    </table>
    <div style="text-align: center; margin-top: 2px; font-size: {{ max(6, $fontPt - 1) }}pt;">
        {{ \App\Services\Pdf\PdfService::numeroALetras($resumen['total_ventas']) }} SOLES
    </div>
</body>
</html>
