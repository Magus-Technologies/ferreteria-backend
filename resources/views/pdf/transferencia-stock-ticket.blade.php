<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $nroDoc }}</title>
    <style>
        {!! $font_face_css ?? '' !!}

        @page {
            size: 80mm auto;
            margin: 3mm;
        }
        body {
            font-family: "{{ $est['fuente'] ?? 'Helvetica' }}", Helvetica, Arial, sans-serif;
            font-size: {{ $est['font_pt'] ?? 7 }}pt;
            color: {{ $est['color_texto'] ?? '#000' }};
            line-height: 1.3;
            width: 74mm;
            margin: 0;
            padding: 0;
        }
        table { border-collapse: collapse; width: 100%; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-bold { font-weight: bold; }
        .separator { border-top: 1px dashed {{ $est['color_borde'] ?? '#000' }}; margin: 4px 0; }
        .section-title {
            font-weight: bold;
            text-align: center;
            margin-bottom: 2px;
            padding-top: 4px;
            border-top: 1px dashed {{ $est['color_borde'] ?? '#000' }};
        }
    </style>
</head>
<body>
    {{-- Header --}}
    <div class="text-center" style="margin-bottom: 4px;">
        @if(!empty($logoPath) && !($msg['ocultar_logo'] ?? false))
            <img src="{{ $logoPath }}" style="max-height: 120px; max-width: 180px;" alt="Logo">
        @endif
        <div style="margin-top: 2px;">
            <div style="{{ $bloques['empresa_razon']['css'] ?? '' }}">{{ $empresa->razon_social }}</div>
            <div style="{{ $bloques['caja_ruc']['css'] ?? '' }}">R.U.C. {{ $empresa->ruc }}</div>
            <div style="{{ $bloques['empresa_direccion']['css'] ?? '' }}">{{ $empresa->direccion }}</div>
            @if($empresa->telefono ?? $empresa->celular ?? null)
                <div style="{{ $bloques['empresa_direccion']['css'] ?? '' }}"><span class="text-bold">Cel:</span> {{ $empresa->telefono ?? $empresa->celular }}</div>
            @endif
            @if($empresa->email ?? null)
                <div style="{{ $bloques['empresa_direccion']['css'] ?? '' }}"><span class="text-bold">Email:</span> {{ $empresa->email }}</div>
            @endif
        </div>
    </div>

    <div class="separator"></div>

    <div class="text-center" style="padding: 4px 0;">
        <div style="{{ $bloques['caja_tipo']['css'] ?? '' }}">{{ $tipoDoc }} ELECTRÓNICA</div>
        <div style="{{ $bloques['caja_numero']['css'] ?? '' }}">{{ $nroDoc }}</div>
    </div>

    <div class="separator"></div>

    {{-- Info --}}
    <div style="padding: 2px 0 6px;">
        <table>
            <tr>
                <td style="width: 50%; vertical-align: top; padding-right: 4px;">
                    <table>
                        <tr><td style="{{ $bloques['info_label']['css'] ?? '' }}">F. EMISIÓN:</td></tr>
                        <tr><td style="{{ $bloques['info_valor']['css'] ?? '' }}">{{ \App\Services\Pdf\PdfService::formatFecha($transferencia->fecha) }}</td></tr>
                        <tr><td style="height: 3px;"></td></tr>
                        <tr><td style="{{ $bloques['info_label']['css'] ?? '' }}">ORIGEN:</td></tr>
                        <tr><td style="{{ $bloques['info_valor']['css'] ?? '' }}">{{ $transferencia->almacenOrigen->name ?? '-' }}</td></tr>
                        <tr><td style="height: 3px;"></td></tr>
                        <tr><td style="{{ $bloques['info_label']['css'] ?? '' }}">USUARIO:</td></tr>
                        <tr><td style="{{ $bloques['info_valor']['css'] ?? '' }}">{{ $transferencia->user->name ?? '-' }}</td></tr>
                    </table>
                </td>
                <td style="width: 50%; vertical-align: top; padding-left: 4px;">
                    <table>
                        <tr><td style="{{ $bloques['info_label']['css'] ?? '' }}">F. TRASLADO:</td></tr>
                        <tr><td style="{{ $bloques['info_valor']['css'] ?? '' }}">{{ \App\Services\Pdf\PdfService::formatFecha($transferencia->fecha_traslado) }}</td></tr>
                        <tr><td style="height: 3px;"></td></tr>
                        <tr><td style="{{ $bloques['info_label']['css'] ?? '' }}">DESTINO:</td></tr>
                        <tr><td style="{{ $bloques['info_valor']['css'] ?? '' }}">{{ $transferencia->almacenDestino->name ?? '-' }}</td></tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>

    {{-- Motivo --}}
    @if($transferencia->motivo ?? null)
        <div class="section-title" style="{{ $bloques['info_label']['css'] ?? '' }}">MOTIVO DE TRASLADO</div>
        <div style="{{ $bloques['info_valor']['css'] ?? '' }} padding: 3px;">{{ $transferencia->motivo->name ?? $transferencia->motivo }}</div>
    @endif

    {{-- Tabla productos --}}
    <div class="section-title" style="{{ $bloques['obs_label']['css'] ?? '' }}">PRODUCTOS</div>
    <table style="font-size: 5pt;">
        <tr>
            <td colspan="3" style="{{ $bloques['tabla_header']['css'] ?? '' }} font-size: 5pt; text-align: left; border-bottom: 1px solid {{ $est['color_borde'] ?? '#000' }};">Producto</td>
        </tr>
        <tr style="border-bottom: 1px solid {{ $est['color_borde'] ?? '#000' }};">
            <td style="{{ $bloques['tabla_header']['css'] ?? '' }} font-size: 5pt; text-align: left; width: 34%;">Cód.</td>
            <td style="{{ $bloques['tabla_header']['css'] ?? '' }} font-size: 5pt; text-align: center; width: 33%;">Unidad</td>
            <td style="{{ $bloques['tabla_header']['css'] ?? '' }} font-size: 5pt; text-align: center; width: 33%;">Cant.</td>
        </tr>
        @foreach($productos as $i => $p)
        @php $bgFila = $i % 2 !== 0 ? ' background-color: #f9f9f9;' : ''; @endphp
        <tr style="{{ $bgFila }}">
            <td colspan="3" style="{{ $bloques['tabla_fila']['css'] ?? '' }} font-size: 6pt; font-weight: bold; padding-top: 3px;">{{ $p['nombre'] }}</td>
        </tr>
        <tr style="border-bottom: 1px solid {{ $est['color_borde'] ?? '#000' }};{{ $bgFila }}">
            <td style="{{ $bloques['tabla_fila']['css'] ?? '' }} font-size: 5pt; padding: 0 0 3px;">{{ $p['codigo'] }}</td>
            <td style="{{ $bloques['tabla_fila']['css'] ?? '' }} font-size: 5pt; text-align: center; padding: 0 0 3px;">{{ $p['unidad'] }}</td>
            <td style="{{ $bloques['tabla_fila']['css'] ?? '' }} font-size: 5pt; text-align: center; padding: 0 0 3px;">{{ \App\Helpers\Formato::cantidad($p['cantidad']) }}</td>
        </tr>
        @endforeach
    </table>

    {{-- Total --}}
    <table style="margin-top: 4px;">
        <tr style="border-top: 2px solid {{ $est['color_borde'] ?? '#000' }}; background-color: #f0f0f0;">
            <td style="{{ $bloques['total_label']['css'] ?? '' }} padding: 4px;">TOTAL</td>
            <td style="{{ $bloques['total_valor']['css'] ?? '' }} padding: 4px;">S/ {{ number_format($total, 2) }}</td>
        </tr>
    </table>

    {{-- Son --}}
    <div style="{{ $bloques['son']['css'] ?? '' }} font-size: 5pt; padding-top: 2px;">
        SON: {{ $son ?? '' }}
    </div>

    {{-- Observaciones --}}
    @if($transferencia->descripcion)
    <div class="section-title" style="{{ $bloques['obs_label']['css'] ?? '' }}">{{ $msg['label_observaciones'] ?? 'OBSERVACIONES' }}</div>
    <div style="padding: 3px; {{ $bloques['obs_valor']['css'] ?? '' }}">
        {{ $transferencia->descripcion }}
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
