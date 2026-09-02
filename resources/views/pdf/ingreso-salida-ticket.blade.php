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

    <div style="padding: 4px 0;">
        <div style="{{ $bloques['caja_tipo']['css'] ?? '' }}">{{ $tipoDoc }}</div>
        <div style="{{ $bloques['caja_numero']['css'] ?? '' }}">{{ $nroDoc }}</div>
    </div>

    <div class="separator"></div>

    {{-- Info en 2 columnas --}}
    <div style="padding: 2px 0 6px;">
        <table>
            <tr>
                <td style="width: 50%; vertical-align: top; padding-right: 4px;">
                    <table>
                        <tr>
                            <td style="{{ $bloques['info_label']['css'] ?? '' }}">FECHA DE EMISI&Oacute;N:</td>
                        </tr>
                        <tr>
                            <td style="{{ $bloques['info_valor']['css'] ?? '' }}">{{ \App\Services\Pdf\PdfService::formatFecha($ingreso->fecha) }}</td>
                        </tr>
                        <tr><td style="height: 3px;"></td></tr>
                        <tr>
                            <td style="{{ $bloques['info_label']['css'] ?? '' }}">ALMAC&Eacute;N:</td>
                        </tr>
                        <tr>
                            <td style="{{ $bloques['info_valor']['css'] ?? '' }}">{{ $ingreso->almacen->name ?? '-' }}</td>
                        </tr>
                        <tr><td style="height: 3px;"></td></tr>
                        <tr>
                            <td style="{{ $bloques['info_label']['css'] ?? '' }}">USUARIO:</td>
                        </tr>
                        <tr>
                            <td style="{{ $bloques['info_valor']['css'] ?? '' }}">{{ $ingreso->user->name ?? '-' }}</td>
                        </tr>
                    </table>
                </td>
                <td style="width: 50%; vertical-align: top; padding-left: 4px;">
                    <table>
                        <tr>
                            <td style="{{ $bloques['info_label']['css'] ?? '' }}">PROVEEDOR:</td>
                        </tr>
                        <tr>
                            <td style="{{ $bloques['info_valor']['css'] ?? '' }}">{{ $ingreso->proveedor->razon_social ?? '-' }}</td>
                        </tr>
                        <tr><td style="height: 3px;"></td></tr>
                        <tr>
                            <td style="{{ $bloques['info_label']['css'] ?? '' }}">TIPO DE {{ str_contains($tipoDoc, 'INGRESO') ? 'INGRESO' : 'SALIDA' }}:</td>
                        </tr>
                        <tr>
                            <td style="{{ $bloques['info_valor']['css'] ?? '' }}">{{ $ingreso->tipoIngreso->name ?? '-' }}</td>
                        </tr>
                        <tr><td style="height: 3px;"></td></tr>
                        <tr>
                            <td style="{{ $bloques['info_label']['css'] ?? '' }}">{{ $msg['label_observaciones'] ?? 'OBSERVACIONES' }}:</td>
                        </tr>
                        <tr>
                            <td style="{{ $bloques['info_valor']['css'] ?? '' }}">{{ $ingreso->descripcion ?? '-' }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>

    {{-- Tabla productos --}}
    <div class="section-title" style="{{ $bloques['obs_label']['css'] ?? '' }}">PRODUCTOS</div>
    <table style="font-size: 5pt; table-layout: fixed; width: 100%;">
        <tr>
            <td colspan="7" style="{{ $bloques['tabla_header']['css'] ?? '' }} font-size: 5pt; text-align: left; border-bottom: 1px solid {{ $est['color_borde'] ?? '#000' }};">Producto</td>
        </tr>
        <tr style="border-bottom: 1px solid {{ $est['color_borde'] ?? '#000' }};">
            <td style="{{ $bloques['tabla_header']['css'] ?? '' }} font-size: 5pt; text-align: left; width: 12%;">C&oacute;d.</td>
            <td style="{{ $bloques['tabla_header']['css'] ?? '' }} font-size: 5pt; text-align: right; width: 14%;">Cant.</td>
            <td style="{{ $bloques['tabla_header']['css'] ?? '' }} font-size: 5pt; text-align: left; width: 16%;">Unid.</td>
            <td style="{{ $bloques['tabla_header']['css'] ?? '' }} font-size: 5pt; text-align: center; width: 16%;">St.Ant</td>
            <td style="{{ $bloques['tabla_header']['css'] ?? '' }} font-size: 5pt; text-align: center; width: 16%;">St.Nue</td>
            <td style="{{ $bloques['tabla_header']['css'] ?? '' }} font-size: 5pt; text-align: right; width: 14%;">Costo</td>
            <td style="{{ $bloques['tabla_header']['css'] ?? '' }} font-size: 5pt; text-align: right; width: 12%;">Total</td>
        </tr>
        @foreach($productos as $i => $p)
        @php
            $totalProd = ($p['costo'] ?? 0) * ($p['cantidad'] ?? 0);
        @endphp
        <tr style="background-color: {{ $i % 2 === 0 ? '#fff' : '#f9f9f9' }};">
            {{-- Línea 1: nombre del producto a todo el ancho --}}
            <td colspan="7" style="{{ $bloques['tabla_fila']['css'] ?? '' }} font-size: 6pt; font-weight: bold; padding-top: 3px; word-wrap: break-word;">
                {{ $i + 1 }}. {{ $p['nombre'] }}
            </td>
        </tr>
        <tr style="background-color: {{ $i % 2 === 0 ? '#fff' : '#f9f9f9' }}; border-bottom: 1px dotted {{ $est['color_borde'] ?? '#999' }};">
            <td style="{{ $bloques['tabla_fila']['css'] ?? '' }} font-size: 5pt; padding-bottom: 2px;">{{ $p['codigo'] }}</td>
            <td style="{{ $bloques['tabla_fila']['css'] ?? '' }} font-size: 5pt; text-align: right; padding-bottom: 2px;">{{ number_format($p['cantidad'], 2) }}</td>
            <td style="{{ $bloques['tabla_fila']['css'] ?? '' }} font-size: 5pt; padding-bottom: 2px;">{{ $p['unidad'] }}</td>
            <td style="{{ $bloques['tabla_fila']['css'] ?? '' }} font-size: 5pt; text-align: center; padding-bottom: 2px;">{{ $p['stock_anterior_f'] }}</td>
            <td style="{{ $bloques['tabla_fila']['css'] ?? '' }} font-size: 5pt; text-align: center; padding-bottom: 2px;">{{ $p['stock_nuevo_f'] }}</td>
            <td style="{{ $bloques['tabla_fila']['css'] ?? '' }} font-size: 5pt; text-align: right; padding-bottom: 2px;">{{ number_format($p['costo'], 2) }}</td>
            <td style="{{ $bloques['tabla_fila']['css'] ?? '' }} font-size: 5pt; text-align: right; padding-bottom: 2px;">{{ number_format($totalProd, 2) }}</td>
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
    <div style="{{ $bloques['son']['css'] ?? '' }} text-align: center; margin-top: 2px;">
        {{ $son }} SOLES
    </div>

    {{-- Observaciones --}}
    <div style="margin-top: 4px;">
        <div style="{{ $bloques['obs_label']['css'] ?? '' }}">{{ $msg['label_observaciones'] ?? 'OBSERVACIONES' }}</div>
        <div style="{{ $bloques['obs_valor']['css'] ?? '' }}">{{ $ingreso->descripcion ?? '-' }}</div>
    </div>

    {{-- Footer --}}
    <div style="margin-top: 6px; padding-top: 4px; border-top: 1px dashed #000;">
        <div style="text-align: center; font-size: 4pt; color: #999;">
            Documento generado el {{ now()->format('d/m/Y H:i:s') }}
        </div>
    </div>
</body>
</html>
