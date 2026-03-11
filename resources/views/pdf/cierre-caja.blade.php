@extends('pdf.layout.document')

@section('content')
    {{-- Header --}}
    @include('pdf.layout.header', [
        'empresa' => $empresa,
        'logoPath' => $logoPath,
        'tipoDocumentoTitulo' => 'CIERRE DE CAJA',
        'numeroDocumento' => $nroDoc,
    ])

    {{-- Info del cierre --}}
    @include('pdf.layout.info-grid', ['filas' => [
        [
            'F. Apertura' => \App\Services\Pdf\PdfService::formatFecha($cierre->fecha_apertura, 'd/m/Y H:i'),
            'F. Cierre' => $cierre->fecha_cierre ? \App\Services\Pdf\PdfService::formatFecha($cierre->fecha_cierre, 'd/m/Y H:i') : 'Pendiente',
        ],
        [
            'Caja' => $cierre->cajaPrincipal->name ?? '-',
            'Estado' => $cierre->estado === 'abierta' ? 'ABIERTA' : 'CERRADA',
        ],
        [
            'Usuario' => $cierre->user->name ?? '-',
            'Supervisor' => $cierre->supervisor->name ?? '-',
        ],
    ]])

    {{-- Resumen de Saldos --}}
    <div style="margin-top: 8px;">
        <div style="font-size: 10pt; font-weight: bold; margin-bottom: 4px; padding: 3px; background-color: #f0f0f0; border-left: 3px solid #333;">
            RESUMEN DE SALDOS
        </div>
        <table style="width: 100%; font-size: 9pt; border-collapse: collapse;">
            <tr style="border-bottom: 1px solid #ddd;">
                <td style="padding: 4px 8px; font-weight: bold;">Efectivo Inicial:</td>
                <td style="padding: 4px 8px; text-align: right;">S/ {{ number_format($resumen['efectivo_inicial'], 2) }}</td>
            </tr>
        </table>
    </div>

    {{-- Movimientos de Caja --}}
    <div style="margin-top: 8px;">
        <div style="font-size: 10pt; font-weight: bold; margin-bottom: 4px; padding: 3px; background-color: #f0f0f0; border-left: 3px solid #333;">
            MOVIMIENTOS DE CAJA
        </div>
        <table style="width: 100%; font-size: 9pt; border-collapse: collapse;">
            @if($otrosIngresos > 0)
            <tr style="border-bottom: 1px solid #ddd;">
                <td style="padding: 4px 8px;">Otros Ingresos:</td>
                <td style="padding: 4px 8px; text-align: right;">S/ {{ number_format($otrosIngresos, 2) }}</td>
            </tr>
            @endif
            @if($resumen['total_prestamos_recibidos'] > 0)
            <tr style="border-bottom: 1px solid #ddd;">
                <td style="padding: 4px 8px;">Préstamos Recibidos ({{ count($resumen['prestamos_recibidos']) }}):</td>
                <td style="padding: 4px 8px; text-align: right;">S/ {{ number_format($resumen['total_prestamos_recibidos'], 2) }}</td>
            </tr>
            @endif
            @if($gastos > 0)
            <tr style="border-bottom: 1px solid #ddd;">
                <td style="padding: 4px 8px;">Gastos:</td>
                <td style="padding: 4px 8px; text-align: right; color: red;">S/ {{ number_format($gastos, 2) }}</td>
            </tr>
            @endif
            @if($resumen['total_prestamos_dados'] > 0)
            <tr style="border-bottom: 1px solid #ddd;">
                <td style="padding: 4px 8px;">Préstamos Dados ({{ count($resumen['prestamos_dados']) }}):</td>
                <td style="padding: 4px 8px; text-align: right; color: red;">S/ {{ number_format($resumen['total_prestamos_dados'], 2) }}</td>
            </tr>
            @endif
            @if(!empty($resumen['movimientos_internos']) && count($resumen['movimientos_internos']) > 0)
            <tr style="border-bottom: 1px solid #ddd; background-color: #e3f2fd;">
                <td style="padding: 4px 8px;">Movimientos Internos ({{ count($resumen['movimientos_internos']) }}) (no afecta total):</td>
                <td style="padding: 4px 8px; text-align: right;">
                    @php
                        $totalMovInt = collect($resumen['movimientos_internos'])->sum('monto');
                    @endphp
                    S/ {{ number_format($totalMovInt, 2) }}
                </td>
            </tr>
            @endif
        </table>
    </div>

    {{-- Métodos de Pago --}}
    @php
        $filasMetodos = [];
        foreach ($resumen['detalle_metodos_pago'] as $metodo) {
            $filasMetodos[] = [
                $metodo['label'],
                $metodo['cantidad_transacciones'] ?? 0,
                'S/ ' . number_format($metodo['total'] ?? 0, 2),
            ];
        }
    @endphp
    @include('pdf.layout.table', [
        'columnas' => [
            ['label' => 'MÉTODO DE PAGO', 'width' => '50%', 'align' => 'left'],
            ['label' => 'CANT.', 'width' => '20%', 'align' => 'center'],
            ['label' => 'TOTAL', 'width' => '30%', 'align' => 'right'],
        ],
        'filas' => $filasMetodos,
        'minFilas' => 3,
    ])

    {{-- Totales Generales --}}
    <div style="margin-top: 8px;">
        <table style="width: 50%; margin-left: auto; font-size: 9pt; border-collapse: collapse;">
            <tr style="border-bottom: 1px solid #ddd;">
                <td style="padding: 4px 8px; font-weight: bold;">Resumen Ventas:</td>
                <td style="padding: 4px 8px; text-align: right;">S/ {{ number_format($resumen['total_ventas'], 2) }}</td>
            </tr>
            <tr style="border-bottom: 1px solid #ddd;">
                <td style="padding: 4px 8px; font-weight: bold;">Resumen Ingresos:</td>
                <td style="padding: 4px 8px; text-align: right;">S/ {{ number_format($resumen['total_ingresos'], 2) }}</td>
            </tr>
            <tr style="border-bottom: 1px solid #ddd;">
                <td style="padding: 4px 8px; font-weight: bold;">Resumen Egresos:</td>
                <td style="padding: 4px 8px; text-align: right; color: red;">S/ {{ number_format($resumen['total_egresos'], 2) }}</td>
            </tr>
            <tr style="background-color: #f0f0f0;">
                <td style="padding: 6px 8px; font-weight: bold; font-size: 10pt;">Total en Caja (Efectivo):</td>
                <td style="padding: 6px 8px; text-align: right; font-weight: bold; font-size: 10pt;">S/ {{ number_format($montoEsperado, 2) }}</td>
            </tr>
        </table>
    </div>

    {{-- Cierre Físico --}}
    <div style="margin-top: 8px;">
        <div style="font-size: 10pt; font-weight: bold; margin-bottom: 4px; padding: 3px; background-color: #f0f0f0; border-left: 3px solid #333;">
            CIERRE FÍSICO
        </div>
        @if($cierre->monto_cierre_efectivo !== null)
        <table style="width: 50%; margin-left: auto; font-size: 9pt; border-collapse: collapse;">
            <tr style="border-bottom: 1px solid #ddd;">
                <td style="padding: 4px 8px;">Dinero Efectivo:</td>
                <td style="padding: 4px 8px; text-align: right;">S/ {{ number_format($montoCierre, 2) }}</td>
            </tr>
            <tr style="border-bottom: 1px solid #ddd;">
                <td style="padding: 4px 8px;">Total Cuentas:</td>
                <td style="padding: 4px 8px; text-align: right;">S/ {{ number_format($totalCuentas, 2) }}</td>
            </tr>
            <tr style="background-color: #f0f0f0;">
                <td style="padding: 6px 8px; font-weight: bold;">Total Cierre Físico:</td>
                <td style="padding: 6px 8px; text-align: right; font-weight: bold;">S/ {{ number_format($montoCierre + $totalCuentas, 2) }}</td>
            </tr>
        </table>
        @else
        <div style="padding: 8px; background-color: #fff3cd; border: 1px solid #ffc107; text-align: center; font-size: 9pt; color: #856404;">
            Pendiente de cierre
        </div>
        @endif
    </div>

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
        <div style="margin-top: 8px;">
            <div style="font-size: 10pt; font-weight: bold; margin-bottom: 4px; padding: 3px; background-color: #f0f0f0; border-left: 3px solid #333;">
                DESGLOSE DE DENOMINACIONES
            </div>
            <table style="width: 50%; margin-left: auto; font-size: 9pt; border-collapse: collapse;">
                <tr style="border-bottom: 2px solid #333;">
                    <th style="padding: 4px 8px; text-align: left;">Denominación</th>
                    <th style="padding: 4px 8px; text-align: center; width: 60px;">Cant.</th>
                    <th style="padding: 4px 8px; text-align: right; width: 80px;">Total</th>
                </tr>
                @foreach($denomsConValor as $i => $denom)
                    @php
                        $cantidad = $conteo[$denom['key']] ?? 0;
                        $subtotalDenom = $cantidad * $denom['valor'];
                    @endphp
                    <tr style="border-bottom: 1px solid #eee; background-color: {{ $i % 2 === 0 ? '#fff' : '#f9f9f9' }};">
                        <td style="padding: 3px 8px;">{{ $denom['label'] }}</td>
                        <td style="padding: 3px 8px; text-align: center;">{{ $cantidad }}</td>
                        <td style="padding: 3px 8px; text-align: right;">S/ {{ number_format($subtotalDenom, 2) }}</td>
                    </tr>
                @endforeach
                <tr style="background-color: #f0f0f0; border-top: 2px solid #333;">
                    <td style="padding: 4px 8px; font-weight: bold;">Total</td>
                    <td></td>
                    <td style="padding: 4px 8px; text-align: right; font-weight: bold;">S/ {{ number_format($montoCierre, 2) }}</td>
                </tr>
            </table>
        </div>
        @endif
    @endif

    {{-- Diferencias --}}
    <div style="margin-top: 8px;">
        <div style="font-size: 10pt; font-weight: bold; margin-bottom: 4px; padding: 3px; background-color: #f0f0f0; border-left: 3px solid #333;">
            DIFERENCIAS
        </div>
        <table style="width: 50%; margin-left: auto; font-size: 9pt; border-collapse: collapse;">
            <tr style="background-color: #e3f2fd; border-bottom: 1px solid #ddd;">
                <td style="padding: 4px 8px; font-weight: bold;">Monto Esperado:</td>
                <td style="padding: 4px 8px; text-align: right; font-weight: bold;">S/ {{ number_format($montoEsperado, 2) }}</td>
            </tr>
            @if($cierre->monto_cierre_efectivo !== null)
            <tr style="border-bottom: 1px solid #ddd;">
                <td style="padding: 4px 8px;">Sobrante:</td>
                <td style="padding: 4px 8px; text-align: right; color: green;">S/ {{ number_format($sobrante, 2) }}</td>
            </tr>
            <tr style="border-bottom: 1px solid #ddd;">
                <td style="padding: 4px 8px;">Faltante:</td>
                <td style="padding: 4px 8px; text-align: right; color: red;">S/ {{ number_format($faltante, 2) }}</td>
            </tr>
            <tr style="background-color: {{ $diferencia < 0 ? '#ffebee' : '#e8f5e9' }};">
                <td style="padding: 6px 8px; font-weight: bold; font-size: 10pt;">Diferencia Total:</td>
                <td style="padding: 6px 8px; text-align: right; font-weight: bold; font-size: 10pt; color: {{ $diferencia < 0 ? 'red' : 'green' }};">
                    S/ {{ number_format($diferencia, 2) }}
                </td>
            </tr>
            @else
            <tr>
                <td colspan="2" style="padding: 8px; background-color: #fff3cd; text-align: center; color: #856404;">
                    Pendiente de cierre
                </td>
            </tr>
            @endif
        </table>
    </div>

    {{-- Observaciones --}}
    <div style="margin-top: 8px;">
        <div style="font-size: 10pt; font-weight: bold; margin-bottom: 4px; padding: 3px; background-color: #f0f0f0; border-left: 3px solid #333;">
            OBSERVACIONES
        </div>
        <div style="padding: 6px 8px; background-color: #fffbf0; border: 1px solid #e0e0e0; font-size: 9pt;">
            {{ $cierre->comentarios ?: '-' }}
        </div>
    </div>

    {{-- Estado del Cierre --}}
    @if($cierre->monto_cierre_efectivo !== null)
    <div style="margin-top: 10px;">
        <div style="padding: 10px; text-align: center; font-size: 12pt; font-weight: bold; background-color: {{ $diferencia == 0 ? '#e8f5e9' : '#ffebee' }}; border: 2px solid {{ $diferencia == 0 ? 'green' : 'red' }}; color: {{ $diferencia == 0 ? 'green' : 'red' }};">
            {{ $diferencia == 0 ? '✓ CAJA CUADRADA' : ($diferencia > 0 ? '⚠ SOBRANTE DE S/ ' . number_format($sobrante, 2) : '⚠ FALTANTE DE S/ ' . number_format($faltante, 2)) }}
        </div>
    </div>
    @endif

    {{-- Total Ventas --}}
    <div style="margin-top: 10px; border-top: 2px solid #000; padding-top: 6px;">
        <table style="width: 100%; font-size: 10pt;">
            <tr style="background-color: #f0f0f0;">
                <td style="padding: 6px 8px; font-weight: bold;">TOTAL VENTAS</td>
                <td style="padding: 6px 8px; font-weight: bold; text-align: right;">S/ {{ number_format($resumen['total_ventas'], 2) }}</td>
            </tr>
        </table>
    </div>
@endsection
