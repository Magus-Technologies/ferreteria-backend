<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $titulo ?? 'Ticket' }}</title>
    <style>
        @page {
            size: 80mm auto;
            margin: 3mm;
        }
        {!! $font_face_css ?? '' !!}
        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 7pt;
            color: #000;
            line-height: 1.3;
            width: 74mm;
            margin: 0;
            padding: 0;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-bold { font-weight: bold; }
        .separator { border-top: 1px dashed #000; margin: 4px 0; }
        table { width: 100%; border-collapse: collapse; }
    </style>
</head>
<body>
    {{-- Header: Logo + Empresa --}}
    <div class="text-center" style="margin-bottom: 4px;">
        @if($logoPath)
            <img src="{{ $logoPath }}" style="max-height: 120px; max-width: 180px;" alt="Logo">
        @endif
        <div style="margin-top: 2px;">
            <div style="{{ $bloques['empresa_razon']['css'] ?? 'font-weight:bold;font-size:9pt;' }}">{{ $empresa->razon_social }}</div>
            <div style="{{ $bloques['caja_ruc']['css'] ?? 'font-weight:bold;' }}">R.U.C. {{ $empresa->ruc }}</div>
            <div style="{{ $bloques['empresa_direccion']['css'] ?? '' }}">{{ $empresa->direccion }}</div>
            <div style="{{ $bloques['empresa_direccion']['css'] ?? '' }}"><span class="text-bold">Cel:</span> {{ $empresa->telefono }}</div>
            <div style="{{ $bloques['empresa_direccion']['css'] ?? '' }}"><span class="text-bold">Email:</span> {{ $empresa->email }}</div>
        </div>
    </div>

    <div class="separator"></div>

    {{-- Tipo documento y numero --}}
    <div style="padding: 4px 0; text-align: center;">
        <div style="{{ $bloques['caja_tipo']['css'] ?? 'font-weight:bold;font-size:9pt;text-align:center;' }}">{{ $tipoDocumentoTitulo }}</div>
        <div style="{{ $bloques['caja_numero']['css'] ?? 'font-weight:bold;font-size:9pt;text-align:center;' }}">{{ $numeroDocumento }}</div>
    </div>

    <div class="separator"></div>

    {{-- Info cliente --}}
    <div style="padding: 2px 0 6px;">
        <table>
            <tr>
                <td style="{{ $bloques['info_label']['css'] ?? 'font-weight:bold;font-size:5pt;' }}">
                    F. EMISI&Oacute;N:
                    <span style="{{ $bloques['info_valor']['css'] ?? 'font-size:5pt;' }}">{{ \App\Services\Pdf\PdfService::formatFecha($cotizacion->fecha) }}</span>
                </td>
            @php($formaPago = $cotizacion->forma_de_pago ?? '')
            @if(!(empty($formaPago) || stripos($formaPago, 'contado') !== false || $formaPago === 'co'))
            <td style="{{ $bloques['info_label']['css'] ?? 'font-weight:bold;font-size:5pt;' }}">
                F. VENCIMIENTO:
                <span style="{{ $bloques['info_valor']['css'] ?? 'font-size:5pt;' }}">{{ \App\Services\Pdf\PdfService::formatFecha($cotizacion->fecha_vencimiento) }}</span>
            </td>
            @endif
            </tr>
        </table>
        <div style="margin-top: 1px;">
            <span style="{{ $bloques['info_label']['css'] ?? 'font-weight:bold;font-size:5pt;' }}">{{ strlen($clienteDocumento) === 11 ? 'RUC:' : 'DNI:' }}</span>
            <span style="{{ $bloques['info_valor']['css'] ?? 'font-size:5pt;' }}">{{ $clienteDocumento }}</span>
        </div>
        <div style="margin-top: 1px;">
            <span style="{{ $bloques['info_label']['css'] ?? 'font-weight:bold;font-size:5pt;' }}">CLIENTE:</span>
            <span style="{{ $bloques['info_valor']['css'] ?? 'font-size:5pt;' }}">{{ $clienteNombre }}</span>
        </div>
        @if($clienteDireccion)
        <div style="margin-top: 1px;">
            <span style="{{ $bloques['info_label']['css'] ?? 'font-weight:bold;font-size:5pt;' }}">DIRECCI&Oacute;N:</span>
            <span style="{{ $bloques['info_valor']['css'] ?? 'font-size:5pt;' }}">{{ $clienteDireccion }}</span>
        </div>
        @endif
        <div style="margin-top: 1px;">
            <span style="{{ $bloques['info_label']['css'] ?? 'font-weight:bold;font-size:5pt;' }}">VENDEDOR:</span>
            <span style="{{ $bloques['info_valor']['css'] ?? 'font-size:5pt;' }}">{{ $vendedor }}</span>
        </div>
    </div>

    <div class="separator"></div>

    {{-- Tabla de productos --}}
    <div style="padding-top: 4px;">
        <table>
            <thead>
                <tr style="border-bottom: 1px solid #000;">
                    <th style="text-align:left; width:55%; {{ $bloques['tabla_header']['css'] ?? 'font-weight:bold;font-size:6pt;' }}">Descripci&oacute;n</th>
                    <th style="text-align:left; width:15%; {{ $bloques['tabla_header']['css'] ?? 'font-weight:bold;font-size:6pt;' }}">Cant.</th>
                    <th style="text-align:left; width:15%; {{ $bloques['tabla_header']['css'] ?? 'font-weight:bold;font-size:6pt;' }}">P.U.</th>
                    <th style="text-align:left; width:15%; {{ $bloques['tabla_header']['css'] ?? 'font-weight:bold;font-size:6pt;' }}">Subt.</th>
                </tr>
            </thead>
            <tbody>
                @foreach($productos as $p)
                <tr style="border-bottom: 1px solid #000;">
                    <td style="padding:3px 0; {{ $bloques['tabla_fila']['css'] ?? 'font-size:6pt;' }}">{{ $p['nombre'] }}</td>
                    <td style="padding:3px 0; {{ $bloques['tabla_fila']['css'] ?? 'font-size:6pt;' }}">{{ number_format($p['cantidad'], 0) }}</td>
                    <td style="padding:3px 0; {{ $bloques['tabla_fila']['css'] ?? 'font-size:6pt;' }}">{{ number_format($p['precio'], 2) }}</td>
                    <td style="padding:3px 0; {{ $bloques['tabla_fila']['css'] ?? 'font-size:6pt;' }}">{{ number_format($p['subtotal'], 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Totales --}}
    <div style="margin-top: 4px;">
        <table>
            <tr style="border-bottom: 1px solid #ccc;">
                <td style="{{ $bloques['total_label']['css'] ?? 'font-weight:bold;font-size:8pt;' }}">SUBTOTAL:</td>
                <td style="text-align:right; {{ $bloques['total_valor']['css'] ?? 'font-size:8pt;' }}">S/ {{ number_format($calculos['subtotal'], 2) }}</td>
            </tr>
            <tr style="border-bottom: 1px solid #ccc;">
                <td style="{{ $bloques['total_label']['css'] ?? 'font-weight:bold;font-size:8pt;' }}">T. DESCUENTO:</td>
                <td style="text-align:right; {{ $bloques['total_valor']['css'] ?? 'font-size:8pt;' }}">S/ {{ number_format($calculos['total_descuento'], 2) }}</td>
            </tr>
            <tr style="background-color: #f0f0f0;">
                <td style="{{ $bloques['total_label']['css'] ?? 'font-weight:bold;font-size:9pt;' }}">TOTAL:</td>
                <td style="text-align:right; font-weight:bold; {{ $bloques['total_valor']['css'] ?? 'font-size:9pt;' }}">S/ {{ number_format($calculos['total'], 2) }}</td>
            </tr>
        </table>
    </div>

    {{-- Total en letras --}}
    <div style="margin-top: 4px; {{ $bloques['son']['css'] ?? 'font-size:7pt;' }}">
        {{ $son }} SOLES
    </div>

    {{-- Observaciones --}}
    <div style="margin-top: 4px;">
        <div style="{{ $bloques['obs_label']['css'] ?? 'font-weight:bold;font-size:7pt;' }}">Observaciones:</div>
        <div style="{{ $bloques['obs_valor']['css'] ?? 'font-size:7pt;' }}">{!! nl2br(e($observaciones)) !!}</div>
    </div>

    @if(empty($msg['ocultar_despedida']))
    <div class="separator"></div>
    <div style="margin-top: 2px; text-align: center;">
        <div style="{{ $bloques['despedida_footer']['css'] ?? 'font-size:7pt;font-weight:bold;text-align:center;' }}">{!! ($plantilla->despedida_activo ?? false) && !empty($plantilla->mensaje_despedida) ? $plantilla->mensaje_despedida : 'GRACIAS POR SU PREFERENCIA!' !!}</div>
    </div>
    @endif
</body>
</html>
