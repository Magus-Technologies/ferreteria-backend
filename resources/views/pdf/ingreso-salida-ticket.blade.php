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
        @if(!empty($logoPath))
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
    <table>
        <tr style="border-bottom: 1px solid {{ $est['color_borde'] ?? '#000' }};">
            <td style="{{ $bloques['tabla_header']['css'] ?? '' }} text-align: left;">Producto</td>
            <td style="{{ $bloques['tabla_header']['css'] ?? '' }} text-align: left; width: 35px;">C&oacute;digo</td>
            <td style="{{ $bloques['tabla_header']['css'] ?? '' }} text-align: left; width: 30px;">Cantidad</td>
            <td style="{{ $bloques['tabla_header']['css'] ?? '' }} text-align: left; width: 40px;">Unidad Derivada</td>
            <td style="{{ $bloques['tabla_header']['css'] ?? '' }} width: 35px;">Stock Anterior</td>
            <td style="{{ $bloques['tabla_header']['css'] ?? '' }} width: 35px;">Stock Nuevo</td>
            <td style="{{ $bloques['tabla_header']['css'] ?? '' }} text-align: right; width: 35px;">Costo</td>
        </tr>
        @foreach($productos as $i => $p)
        <tr style="background-color: {{ $i % 2 === 0 ? '#fff' : '#f9f9f9' }};">
            <td colspan="7" style="{{ $bloques['tabla_fila']['css'] ?? '' }} padding-top: 2px;">{{ $p['nombre'] }}</td>
        </tr>
        <tr style="background-color: {{ $i % 2 === 0 ? '#fff' : '#f9f9f9' }};">
            <td></td>
            <td style="{{ $bloques['tabla_fila']['css'] ?? '' }}">{{ $p['codigo'] }}</td>
            <td style="{{ $bloques['tabla_fila']['css'] ?? '' }}">{{ number_format($p['cantidad'], 3) }}</td>
            <td style="{{ $bloques['tabla_fila']['css'] ?? '' }}">{{ $p['unidad'] }}</td>
            <td style="{{ $bloques['tabla_fila']['css'] ?? '' }} text-align: center;">{{ $p['stock_anterior_f'] }}</td>
            <td style="{{ $bloques['tabla_fila']['css'] ?? '' }} text-align: center;">{{ $p['stock_nuevo_f'] }}</td>
            <td style="{{ $bloques['tabla_fila']['css'] ?? '' }} text-align: right;">{{ number_format($p['costo'], 2) }}</td>
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
