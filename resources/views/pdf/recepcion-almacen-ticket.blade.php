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
        <div style="{{ $bloques['caja_tipo']['css'] ?? '' }}">RECEPCIÓN DE ALMACÉN ELECTRÓNICA</div>
        <div style="{{ $bloques['caja_numero']['css'] ?? '' }}">{{ $nroDoc }}</div>
    </div>

    <div class="separator"></div>

    {{-- Info --}}
    <div style="padding: 2px 0 6px;">
        <table>
            <tr>
                <td style="width: 50%; vertical-align: top; padding-right: 4px;">
                    <table>
                        <tr><td style="{{ $bloques['info_label']['css'] ?? '' }}">F. RECEPCIÓN:</td></tr>
                        <tr><td style="{{ $bloques['info_valor']['css'] ?? '' }}">{{ \App\Services\Pdf\PdfService::formatFecha($recepcion->fecha) }}</td></tr>
                        <tr><td style="height: 3px;"></td></tr>
                        <tr><td style="{{ $bloques['info_label']['css'] ?? '' }}">ALMACÉN:</td></tr>
                        <tr><td style="{{ $bloques['info_valor']['css'] ?? '' }}">{{ $src->almacen->name ?? '-' }}</td></tr>
                        <tr><td style="height: 3px;"></td></tr>
                        <tr><td style="{{ $bloques['info_label']['css'] ?? '' }}">F. COMPRA:</td></tr>
                        <tr><td style="{{ $bloques['info_valor']['css'] ?? '' }}">{{ \App\Services\Pdf\PdfService::formatFecha($src->fecha ?? null) }}</td></tr>
                    </table>
                </td>
                <td style="width: 50%; vertical-align: top; padding-left: 4px;">
                    <table>
                        <tr><td style="{{ $bloques['info_label']['css'] ?? '' }}">USUARIO:</td></tr>
                        <tr><td style="{{ $bloques['info_valor']['css'] ?? '' }}">{{ $recepcion->user->name ?? '-' }}</td></tr>
                        <tr><td style="height: 3px;"></td></tr>
                        <tr><td style="{{ $bloques['info_label']['css'] ?? '' }}">DOCUMENTO:</td></tr>
                        <tr><td style="{{ $bloques['info_valor']['css'] ?? '' }}">
                            @if($recepcion->compra)
                                {{ ($recepcion->compra->serie ?? '') . '-' . ($recepcion->compra->numero ?? '') }}
                            @elseif($recepcion->ordenCompra)
                                {{ $recepcion->ordenCompra->codigo ?? '-' }}
                            @else
                                -
                            @endif
                        </td></tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>

    {{-- Proveedor --}}
    <div class="section-title" style="{{ $bloques['info_label']['css'] ?? '' }}">DATOS DEL PROVEEDOR</div>
    <table style="font-size: 6pt;">
        <tr>
            <td style="{{ $bloques['info_label']['css'] ?? '' }} width: 30%;">RUC:</td>
            <td style="{{ $bloques['info_valor']['css'] ?? '' }}">{{ $src->proveedor->ruc ?? '-' }}</td>
        </tr>
        <tr>
            <td style="{{ $bloques['info_label']['css'] ?? '' }}">RAZÓN SOCIAL:</td>
            <td style="{{ $bloques['info_valor']['css'] ?? '' }}">{{ $src->proveedor->razon_social ?? '-' }}</td>
        </tr>
        <tr>
            <td style="{{ $bloques['info_label']['css'] ?? '' }}">GUÍA REMISIÓN:</td>
            <td style="{{ $bloques['info_valor']['css'] ?? '' }}">{{ $src->guia ?? '-' }}</td>
        </tr>
    </table>

    {{-- Transportista --}}
    <div class="section-title" style="{{ $bloques['info_label']['css'] ?? '' }}">DATOS DEL TRANSPORTISTA</div>
    <table style="font-size: 6pt;">
        <tr>
            <td style="{{ $bloques['info_label']['css'] ?? '' }} width: 30%;">RUC:</td>
            <td style="{{ $bloques['info_valor']['css'] ?? '' }}">{{ $recepcion->transportista_ruc ?? '-' }}</td>
        </tr>
        <tr>
            <td style="{{ $bloques['info_label']['css'] ?? '' }}">RAZÓN SOCIAL:</td>
            <td style="{{ $bloques['info_valor']['css'] ?? '' }}">{{ $recepcion->transportista_razon_social ?? '-' }}</td>
        </tr>
        <tr>
            <td style="{{ $bloques['info_label']['css'] ?? '' }}">PLACA:</td>
            <td style="{{ $bloques['info_valor']['css'] ?? '' }}">{{ $recepcion->transportista_placa ?? '-' }}</td>
        </tr>
        <tr>
            <td style="{{ $bloques['info_label']['css'] ?? '' }}">LICENCIA:</td>
            <td style="{{ $bloques['info_valor']['css'] ?? '' }}">{{ $recepcion->transportista_licencia ?? '-' }}</td>
        </tr>
        <tr>
            <td style="{{ $bloques['info_label']['css'] ?? '' }}">NOMBRES:</td>
            <td style="{{ $bloques['info_valor']['css'] ?? '' }}">{{ $recepcion->transportista_name ?? '-' }}</td>
        </tr>
        <tr>
            <td style="{{ $bloques['info_label']['css'] ?? '' }}">GUÍA REM. TRANSP.:</td>
            <td style="{{ $bloques['info_valor']['css'] ?? '' }}">{{ $recepcion->transportista_guia_remision ?? '-' }}</td>
        </tr>
    </table>

    {{-- Tabla productos --}}
    <div class="section-title" style="{{ $bloques['obs_label']['css'] ?? '' }}">PRODUCTOS</div>
    <table style="font-size: 5pt;">
        <tr style="border-bottom: 1px solid {{ $est['color_borde'] ?? '#000' }};">
            <td style="{{ $bloques['tabla_header']['css'] ?? '' }} font-size: 5pt; text-align: left; width: 35px;">Cód.</td>
            <td style="{{ $bloques['tabla_header']['css'] ?? '' }} font-size: 5pt; text-align: left;">Producto</td>
            <td style="{{ $bloques['tabla_header']['css'] ?? '' }} font-size: 5pt; text-align: center; width: 40px;">Unidad</td>
            <td style="{{ $bloques['tabla_header']['css'] ?? '' }} font-size: 5pt; text-align: center; width: 30px;">Cant.</td>
        </tr>
        @foreach($productos as $i => $p)
        <tr style="background-color: {{ $i % 2 === 0 ? '#fff' : '#f9f9f9' }};">
            <td style="{{ $bloques['tabla_fila']['css'] ?? '' }} font-size: 5pt; padding-top: 2px;">{{ $p['codigo'] }}</td>
            <td style="{{ $bloques['tabla_fila']['css'] ?? '' }} font-size: 5pt; padding-top: 2px;">{{ $p['bonificacion'] ? '* ' : '' }}{{ $p['nombre'] }}</td>
            <td style="{{ $bloques['tabla_fila']['css'] ?? '' }} font-size: 5pt; text-align: center; padding-top: 2px;">{{ $p['unidad'] }}</td>
            <td style="{{ $bloques['tabla_fila']['css'] ?? '' }} font-size: 5pt; text-align: center; padding-top: 2px;">{{ number_format($p['cantidad'], 0) }}</td>
        </tr>
        @endforeach
    </table>

    {{-- Total --}}
    <table style="margin-top: 4px;">
        <tr style="border-top: 2px solid {{ $est['color_borde'] ?? '#000' }}; background-color: #f0f0f0;">
            <td style="{{ $bloques['total_label']['css'] ?? '' }} padding: 4px;">TOTAL ITEMS</td>
            <td style="{{ $bloques['total_valor']['css'] ?? '' }} padding: 4px;">{{ number_format($total, 0) }}</td>
        </tr>
    </table>

    {{-- Observaciones --}}
    @if($recepcion->observaciones)
    <div class="section-title" style="{{ $bloques['obs_label']['css'] ?? '' }}">{{ $msg['label_observaciones'] ?? 'OBSERVACIONES' }}</div>
    <div style="padding: 3px; {{ $bloques['obs_valor']['css'] ?? '' }}">
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
