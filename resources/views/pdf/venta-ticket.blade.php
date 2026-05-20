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
        .row-alt { background-color: #f9f9f9; }
        .label { font-weight: bold; text-transform: uppercase; font-size: 5pt; }
        .value { font-size: 5pt; }
    </style>
</head>
<body>
    {{-- Header: Logo + Empresa (bloque separado) --}}
    <div class="text-center" style="margin-bottom: 4px;">
        @if($logoPath)
            <img src="{{ $logoPath }}" style="max-height: 120px; max-width: 180px;" alt="Logo">
        @endif
        <div style="margin-top: 2px;">
            <div style="{{ $bloques['empresa_razon']['css'] ?? 'font-weight:bold; font-size:9pt;' }}">{{ $empresa->razon_social }}</div>
            <div style="{{ $bloques['caja_ruc']['css'] ?? 'font-weight:bold;' }}">R.U.C. {{ $empresa->ruc }}</div>
            <div style="{{ $bloques['empresa_direccion']['css'] ?? '' }}">{{ $empresa->direccion }}</div>
            <div style="{{ $bloques['info_label']['css'] ?? '' }}"><span style="{{ $bloques['info_label']['css'] ?? '' }}">Cel:</span> <span style="{{ $bloques['info_valor']['css'] ?? '' }}">{{ $empresa->telefono }}</span></div>
            <div style="{{ $bloques['info_label']['css'] ?? '' }}"><span style="{{ $bloques['info_label']['css'] ?? '' }}">Email:</span> <span style="{{ $bloques['info_valor']['css'] ?? '' }}">{{ $empresa->email }}</span></div>
        </div>
    </div>

    <div class="separator"></div>

    {{-- Tipo documento y numero (bloques separados) --}}
    <div class="text-center" style="padding: 4px 0;">
        <div style="{{ $bloques['caja_tipo']['css'] ?? 'font-weight:bold; font-size:9pt;text-align:center;' }}">{{ $tipoDocumentoTitulo }}</div>
        <div style="{{ $bloques['caja_numero']['css'] ?? 'font-weight:bold;text-align:center;' }}">{{ $numeroDocumento }}</div>
    </div>

    <div class="separator"></div>

    {{-- Info de la venta (bloque) --}}
    <div style="padding: 2px 0 6px;">
        <table>
            <tr>
                <td style="{{ $bloques['info_label']['css'] ?? 'font-weight:bold;' }}">Forma Pago:</td>
                <td style="{{ $bloques['info_valor']['css'] ?? '' }}">{{ $formaPago }}</td>
            </tr>
            <tr>
                <td style="{{ $bloques['info_label']['css'] ?? 'font-weight:bold; width:50%;' }}">F. Emisi&oacute;n: <span style="{{ $bloques['info_valor']['css'] ?? '' }}">{{ $fechaEmision }}</span></td>
                <td style="{{ $bloques['info_label']['css'] ?? 'width:50%;' }}">Hora: <span style="{{ $bloques['info_valor']['css'] ?? '' }}">{{ $hora }}</span></td>
            </tr>
            @if($esCredito)
            <tr>
                <td class="label">F. Vencimiento: <span class="value">{{ $fechaVencimiento }}</span></td>
                <td class="label">N&deg; Gu&iacute;a: <span class="value">{{ $numeroGuia }}</span></td>
            </tr>
            @else
            <tr>
                <td class="label">N&deg; Gu&iacute;a:</td>
                <td class="value">{{ $numeroGuia }}</td>
            </tr>
            @endif
            <tr>
                <td class="label">Vendedor:</td>
                <td class="value">{{ $vendedor }}</td>
            </tr>
            @if($recomendadoPor)
            <tr>
                <td class="label">Recomendado por:</td>
                <td class="value">{{ $recomendadoPor }}</td>
            </tr>
            @endif
        </table>
    </div>

    <div class="separator"></div>

    {{-- Info del cliente (bloque) --}}
    <div style="padding: 2px 0 6px;">
        <table>
            <tr>
                <td style="{{ $bloques['info_label']['css'] ?? '' }}">{{ strlen($clienteDocumento) === 11 ? 'RUC:' : 'DNI:' }}</td>
                <td style="{{ $bloques['info_valor']['css'] ?? '' }}">{{ $clienteDocumento }}</td>
            </tr>
            <tr>
                <td style="{{ $bloques['info_label']['css'] ?? '' }}">Cliente:</td>
                <td style="{{ $bloques['info_valor']['css'] ?? '' }}">{{ $clienteNombre }}</td>
            </tr>
            @if($clienteDireccion)
            <tr>
                <td style="{{ $bloques['info_label']['css'] ?? '' }}">Direcci&oacute;n:</td>
                <td style="{{ $bloques['info_valor']['css'] ?? '' }}">{{ $clienteDireccion }}</td>
            </tr>
            @endif
        </table>
    </div>

    <div class="separator"></div>

    {{-- Metodos de pago --}}
    @if(count($metodosPago) > 0)
    <div style="padding: 2px 0 4px;">
        <div style="{{ $bloques['info_label']['css'] ?? 'font-weight:bold; font-size:6pt; margin-bottom:2px;' }}">M&eacute;todos de Pago:</div>
        <table>
            @foreach($metodosPago as $mp)
            <tr>
                <td style="{{ $bloques['tabla_fila']['css'] ?? 'font-size:6pt;' }}">{{ $mp['nombre'] }}</td>
                <td style="{{ $bloques['tabla_fila']['css'] ?? 'font-size:6pt; text-align:right;' }}">{{ number_format($mp['monto'], 2) }}</td>
            </tr>
            @if(isset($mp['sobrecargo_aplicado']) && $mp['sobrecargo_aplicado'] > 0)
            <tr>
                <td style="font-size: 5pt; color: #666; padding-left: 6px;">+ Sobrecargo ({{ number_format($mp['sobrecargo_aplicado'] / $mp['monto'] * 100, 1) }}%)</td>
                <td style="font-size: 5pt; text-align: right; color: #666;">{{ number_format($mp['sobrecargo_aplicado'], 2) }}</td>
            </tr>
            @endif
            <tr style="border-bottom: 1px dashed #999;">
                <td class="text-bold" style="font-size: 6pt;">TOTAL</td>
                <td class="text-bold" style="font-size: 6pt; text-align: right;">{{ number_format($mp['monto'] + ($mp['sobrecargo_aplicado'] ?? 0), 2) }}</td>
            </tr>
            @endforeach
        </table>
    </div>
    <div class="separator"></div>
    @endif

    {{-- Tabla de productos --}}
    <div style="padding-top: 4px;">
        <table>
            <thead>
                <tr style="border-bottom: 1px solid #000;">
                    <th style="{{ $bloques['tabla_header']['css'] ?? '' }}; text-align:left; width:40%">Descripci&oacute;n</th>
                    <th style="{{ $bloques['tabla_header']['css'] ?? '' }}; text-align:center; width:10%">Cant.</th>
                    <th style="{{ $bloques['tabla_header']['css'] ?? '' }}; text-align:center; width:10%">Unid.</th>
                    <th style="{{ $bloques['tabla_header']['css'] ?? '' }}; text-align:right; width:15%">P.U.</th>
                    <th style="{{ $bloques['tabla_header']['css'] ?? '' }}; text-align:right; width:15%">Subt.</th>
                </tr>
            </thead>
            <tbody>
                @php $paqueteActual = null; @endphp
                @foreach($productos as $i => $p)
                    @if(!empty($p['paquete_id']) && $p['paquete_id'] !== $paqueteActual)
                        @php $paqueteActual = $p['paquete_id']; @endphp
                        <tr>
                            <td colspan="5" style="font-size: 6pt; padding: 3px 0; font-weight: bold; background-color: #e8e8e8;">
                                [COMBO] {{ $p['paquete_nombre'] ?? 'COMBO' }}
                            </td>
                        </tr>
                    @endif
                    @if(empty($p['paquete_id']) && $paqueteActual !== null)
                        @php $paqueteActual = null; @endphp
                    @endif
                <tr style="border-bottom: 1px solid #000;{{ $i % 2 !== 0 ? ' background-color: #f9f9f9;' : '' }}">
                    <td style="{{ $bloques['tabla_fila']['css'] ?? '' }} {{ !empty($p['paquete_id']) ? ' padding-left: 6px;' : '' }}">{{ $p['nombre'] }}</td>
                    <td style="{{ $bloques['tabla_fila']['css'] ?? '' }}; text-align:center;">{{ number_format($p['cantidad'], 0) }}</td>
                    <td style="{{ $bloques['tabla_fila']['css'] ?? '' }}; text-align:center;">{{ $p['unidad'] }}</td>
                    <td style="{{ $bloques['tabla_fila']['css'] ?? '' }}; text-align:right;">{{ number_format($p['precio'], 2) }}</td>
                    <td style="{{ $bloques['tabla_fila']['css'] ?? '' }}; text-align:right;">{{ number_format($p['subtotal'], 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Totales --}}
    <div style="margin-top: 4px;">
        <table>
            @if($calculos['total_descuento'] > 0)
            <tr style="border-bottom: 1px solid #000;">
                <td style="{{ $bloques['total_label']['css'] ?? '' }}">TOTAL DESCUENTO</td>
                <td style="{{ $bloques['total_valor']['css'] ?? '' }}">{{ number_format($calculos['total_descuento'], 2) }}</td>
            </tr>
            @endif
            <tr style="border-bottom: 1px solid #000;">
                <td style="{{ $bloques['total_label']['css'] ?? '' }}">OP.GRAVADA</td>
                <td style="{{ $bloques['total_valor']['css'] ?? '' }}">{{ number_format($calculos['subtotal'], 2) }}</td>
            </tr>
            <tr style="border-bottom: 1px solid #000;">
                <td style="{{ $bloques['total_label']['css'] ?? '' }}">IGV 18%</td>
                <td style="{{ $bloques['total_valor']['css'] ?? '' }}">{{ number_format($calculos['igv'], 2) }}</td>
            </tr>
            <tr>
                <td style="{{ $bloques['total_label']['css'] ?? '' }}">TOTAL</td>
                <td style="{{ $bloques['total_valor']['css'] ?? '' }}">{{ number_format($calculos['total'], 2) }}</td>
            </tr>
        </table>
    </div>

    {{-- Total en letras (bloque son) --}}
    <div style="margin-top: 4px; {{ $bloques['son']['css'] ?? '' }}">
        {{ $son }} SOLES
    </div>

    {{-- Observaciones (bloque) --}}
    <div style="margin-top: 4px;">
        <div style="{{ $bloques['obs_label']['css'] ?? '' }}">Observaciones:</div>
        <div style="{{ $bloques['obs_valor']['css'] ?? '' }}">{{ $observaciones }}</div>
    </div>

    {{-- Enlace consulta documento (bloque) --}}
    @if(isset($consultaUrl))
    <div class="separator"></div>
    <div class="text-center" style="margin-top: 4px;">
        <div style="{{ $bloques['consulta_leyenda']['css'] ?? 'font-size:6pt;color:#666;' }}">{{ $msg['leyenda_consulta'] ?? 'Consulte su documento en:' }}</div>
        <div style="{{ $bloques['consulta_url']['css'] ?? 'font-size:6pt; font-weight:bold; color:#333; word-break:break-all;' }}">{{ $consultaUrl }}</div>
    </div>
    @endif

    <div class="separator"></div>
    <div class="text-center" style="margin-top: 2px;">
        <div style="{{ $bloques['despedida_footer']['css'] ?? 'font-size:7pt;font-weight:bold;' }}">{!! $plantilla->mensaje_despedida ?? ($msg['leyenda_representacion'] ?? 'GRACIAS POR SU PREFERENCIA!') !!}</div>
    </div>

    {{-- Vales aplicados --}}
    @if(isset($vales) && count($vales) > 0)
        @foreach($vales as $vale)
        <div style="margin-top: 10px; border-top: 2px dashed #000; padding-top: 2px;">
            <div class="text-center" style="font-size: 5pt; color: #666;">
                - - - - - - - - CORTAR AQU&Iacute; - - - - - - - -
            </div>
        </div>
        <div style="border: 1.5px solid #000; border-radius: 4px; padding: 6px; margin-top: 4px;">
            <div class="text-center text-bold" style="background-color: #000; color: #fff; font-size: 8pt; padding: 4px; margin-bottom: 4px; border-radius: 2px;">
                VALE DE COMPRA - {{ $vale['tipo_label'] }}
            </div>
            <div class="text-center text-bold" style="font-size: 7pt; margin-bottom: 3px;">
                {{ $vale['nombre'] }}
            </div>
            <div class="text-center text-bold" style="font-size: 9pt; border: 1px solid #000; padding: 3px; margin-bottom: 4px; border-radius: 2px;">
                {{ $vale['beneficio'] }}
            </div>
            @if($vale['codigo'])
            <div class="text-center" style="background-color: #f0f0f0; padding: 4px; margin-bottom: 4px; border-radius: 2px;">
                <div style="font-size: 6pt;">C&Oacute;DIGO:</div>
                <div class="text-bold" style="font-size: 10pt; letter-spacing: 1px;">{{ $vale['codigo'] }}</div>
            </div>
            @if(isset($vale['barcode_base64']))
            <div class="text-center" style="margin-bottom: 4px;">
                <img src="{{ $vale['barcode_base64'] }}" style="width: 90%; max-width: 60mm; height: auto;" alt="Barcode">
            </div>
            @endif
            @if(isset($vale['qr_base64']))
            <div class="text-center" style="margin-bottom: 4px;">
                <img src="{{ $vale['qr_base64'] }}" style="width: 100px; height: 100px;" alt="QR">
                <div style="font-size: 5pt; color: #666;">Escanea para canjear</div>
            </div>
            @endif
            @endif
            @if($vale['fecha_validez'])
            <div class="text-center" style="font-size: 6pt; margin-bottom: 2px;">
                V&aacute;lido hasta: {{ $vale['fecha_validez'] }}
            </div>
            @endif
            <div class="text-center" style="font-size: 5pt; color: #666; border-top: 1px dashed #999; padding-top: 3px; margin-top: 2px;">
                Boleta: {{ $numeroDocumento }} | {{ $fechaEmision }}
            </div>
        </div>
        @endforeach
    @endif
</body>
</html>
