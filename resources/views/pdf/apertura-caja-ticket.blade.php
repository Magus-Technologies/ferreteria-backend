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
        .bg-blue { background-color: #e3f2fd; }
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
        <div style="{{ $cssCajaTipo }}">APERTURA DE CAJA ELECTRÓNICA</div>
        <div style="{{ $cssCajaNumero }}">{{ $nroDoc }}</div>
    </div>

    <div class="separator"></div>

    {{-- Info de la apertura --}}
    <div style="padding: 2px 0 6px;">
        <table>
            <tr>
                <td style="{{ $cssInfoLabel }} width: 25%;">FECHA:</td>
                <td style="{{ $cssInfoValor }}">{{ \App\Services\Pdf\PdfService::formatFecha($apertura->fecha_apertura) }}</td>
                <td style="{{ $cssInfoLabel }} width: 25%;">HORA:</td>
                <td style="{{ $cssInfoValor }}">{{ \App\Services\Pdf\PdfService::formatFecha($apertura->fecha_apertura, 'H:i') }}</td>
            </tr>
            <tr>
                <td style="{{ $cssInfoLabel }}">CAJA:</td>
                <td style="{{ $cssInfoValor }}" colspan="3">{{ $apertura->cajaPrincipal->name ?? $apertura->cajaPrincipal->nombre ?? '-' }}</td>
            </tr>
            <tr>
                <td style="{{ $cssInfoLabel }}">USUARIO:</td>
                <td style="{{ $cssInfoValor }}" colspan="3">{{ $apertura->user->name ?? '-' }}</td>
            </tr>
        </table>
    </div>

    <div class="separator"></div>

    {{-- Monto de Apertura --}}
    <div class="section-title" style="{{ $cssInfoLabel }}">MONTO DE APERTURA</div>
    <table>
        <tr class="bg-green">
            <td style="{{ $cssTotalLabel }} padding: 3px;">Total Efectivo:</td>
            <td style="{{ $cssTotalValor }} padding: 3px; color: green; text-align: right;">S/ {{ number_format($apertura->monto_apertura, 2) }}</td>
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
                <td style="{{ $cssTotalValor }} text-align: right;">S/ {{ number_format($apertura->monto_apertura, 2) }}</td>
            </tr>
        </table>
        @endif
    @endif

    {{-- Distribución a Vendedores --}}
    @if(count($distribuciones) > 0)
    <div class="section-title" style="{{ $cssInfoLabel }}">DISTRIBUCIÓN A VENDEDORES ({{ count($distribuciones) }})</div>
    <table>
        <tr style="border-bottom: {{ $borderThin }}px solid {{ $colorBorde }};">
            <td style="{{ $cssTablaHeader }}">Vendedor</td>
            <td style="{{ $cssTablaHeader }} text-align: right; width: 50px;">Monto</td>
        </tr>
        @foreach($distribuciones as $i => $dist)
        <tr style="background-color: {{ $i % 2 === 0 ? '#fff' : '#f9f9f9' }};">
            <td style="{{ $cssTablaFila }}">{{ $dist['vendedor'] }}</td>
            <td style="{{ $cssTablaFila }} text-align: right;">S/ {{ number_format($dist['monto'], 2) }}</td>
        </tr>
        @endforeach
        <tr class="bg-gray" style="border-top: {{ $borderThin }}px solid {{ $colorBorde }};">
            <td style="{{ $cssTotalLabel }}">TOTAL DISTRIBUIDO</td>
            <td style="{{ $cssTotalValor }} text-align: right;">S/ {{ number_format($apertura->monto_apertura, 2) }}</td>
        </tr>
    </table>
    @endif

    {{-- Información adicional --}}
    <div style="margin-top: 4px; padding-top: 4px; border-top: {{ $borderThin }}px dashed {{ $colorBorde }};">
        <div class="bg-blue" style="padding: 3px; text-align: center; color: #1565c0; font-size: {{ $fontPt - 1 }}pt;">
            Caja aperturada exitosamente
        </div>
    </div>

    {{-- Pie de página --}}
    <div style="margin-top: 6px; padding-top: 4px; border-top: {{ $borderThin }}px dashed {{ $colorBorde }};">
        <div style="{{ $cssDespedida }}">Gracias por usar nuestro sistema</div>
        <div style="text-align: center; color: #999; margin-top: 2px; font-size: {{ max(6, $fontPt - 2) }}pt;">
            Documento generado el {{ now()->format('d/m/Y H:i:s') }}
        </div>
    </div>
</body>
</html>
