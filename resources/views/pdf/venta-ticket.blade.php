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
        .row-alt { background-color: #f9f9f9; }
        .label { font-weight: bold; text-transform: uppercase; font-size: 5pt; }
        .value { font-size: 5pt; }
    </style>
</head>
<body>
    {{-- Header: Logo + Empresa --}}
    <div class="text-center" style="margin-bottom: 4px;">
        @if($logoPath && !($msg['ocultar_logo'] ?? false))
            <img src="{{ $logoPath }}" style="max-height: 120px; max-width: 180px;" alt="Logo">
        @endif
        <div style="margin-top: 2px;">
            <div style="{{ $bloques['empresa_razon']['css'] ?? 'font-weight:bold;font-size:9pt;' }}">{{ $empresa->razon_social }}</div>
            <div style="{{ $bloques['caja_ruc']['css'] ?? 'font-weight:bold;' }}">R.U.C. {{ $empresa->ruc }}</div>
            <div style="{{ $bloques['empresa_direccion']['css'] ?? '' }}">{{ $empresa->direccion }}</div>
        </div>
    </div>

    <div class="separator"></div>

    {{-- Tipo documento y numero --}}
    <div style="padding: 4px 0; text-align: center;">
        <div style="{{ $bloques['caja_tipo']['css'] ?? 'font-weight:bold;font-size:9pt;text-align:center;' }}">{{ $tipoDocumentoTitulo }}</div>
        <div style="{{ $bloques['caja_numero']['css'] ?? 'font-weight:bold;font-size:9pt;text-align:center;' }}">{{ $numeroDocumento }}</div>
    </div>

    <div class="separator"></div>

    {{-- Info de la venta --}}
    <div style="padding: 2px 0 6px;">
        <table>
            <tr>
                <td style="text-transform:uppercase; {{ $bloques['info_label']['css'] ?? 'font-weight:bold;font-size:5pt;' }}">Forma Pago:</td>
                <td style="{{ $bloques['info_valor']['css'] ?? 'font-size:5pt;' }}">{{ $formaPago }}</td>
            </tr>
            <tr>
                <td style="width:50%; text-transform:uppercase; {{ $bloques['info_label']['css'] ?? 'font-weight:bold;font-size:5pt;' }}">F. Emisi&oacute;n: <span style="text-transform:none; {{ $bloques['info_valor']['css'] ?? 'font-size:5pt;' }}">{{ $fechaEmision }}</span></td>
                <td style="width:50%; text-transform:uppercase; {{ $bloques['info_label']['css'] ?? 'font-weight:bold;font-size:5pt;' }}">Hora: <span style="text-transform:none; {{ $bloques['info_valor']['css'] ?? 'font-size:5pt;' }}">{{ $hora }}</span></td>
            </tr>
            @if($esCredito)
            <tr>
                <td style="text-transform:uppercase; {{ $bloques['info_label']['css'] ?? 'font-weight:bold;font-size:5pt;' }}">F. Vencimiento: <span style="text-transform:none; {{ $bloques['info_valor']['css'] ?? 'font-size:5pt;' }}">{{ $fechaVencimiento }}</span></td>
                <td style="text-transform:uppercase; {{ $bloques['info_label']['css'] ?? 'font-weight:bold;font-size:5pt;' }}">N&deg; Gu&iacute;a: <span style="text-transform:none; {{ $bloques['info_valor']['css'] ?? 'font-size:5pt;' }}">{{ $numeroGuia }}</span></td>
            </tr>
            @else
            <tr>
                <td style="text-transform:uppercase; {{ $bloques['info_label']['css'] ?? 'font-weight:bold;font-size:5pt;' }}">N&deg; Gu&iacute;a:</td>
                <td style="{{ $bloques['info_valor']['css'] ?? 'font-size:5pt;' }}">{{ $numeroGuia }}</td>
            </tr>
            @endif
            <tr>
                <td style="text-transform:uppercase; {{ $bloques['info_label']['css'] ?? 'font-weight:bold;font-size:5pt;' }}">Vendedor:</td>
                <td style="{{ $bloques['info_valor']['css'] ?? 'font-size:5pt;' }}">{{ $vendedor }}</td>
            </tr>
            @if($recomendadoPor)
            <tr>
                <td style="text-transform:uppercase; {{ $bloques['info_label']['css'] ?? 'font-weight:bold;font-size:5pt;' }}">Recomendado por:</td>
                <td style="{{ $bloques['info_valor']['css'] ?? 'font-size:5pt;' }}">{{ $recomendadoPor }}</td>
            </tr>
            @endif
        </table>
    </div>

    <div class="separator"></div>

    {{-- Info del cliente --}}
    <div style="padding: 2px 0 6px;">
        <table>
            <tr>
                <td style="text-transform:uppercase; {{ $bloques['info_label']['css'] ?? 'font-weight:bold;font-size:5pt;' }}">{{ !ctype_digit($clienteDocumento) ? 'DOC:' : (strlen($clienteDocumento) === 11 ? 'RUC:' : 'DNI:') }}</td>
                <td style="{{ $bloques['info_valor']['css'] ?? 'font-size:5pt;' }}">{{ $clienteDocumento }}</td>
            </tr>
            <tr>
                <td style="text-transform:uppercase; {{ $bloques['info_label']['css'] ?? 'font-weight:bold;font-size:5pt;' }}">Cliente:</td>
                <td style="{{ $bloques['info_valor']['css'] ?? 'font-size:5pt;' }}">{{ $clienteNombre }}</td>
            </tr>
            @if($clienteDireccion)
            <tr>
                <td style="text-transform:uppercase; {{ $bloques['info_label']['css'] ?? 'font-weight:bold;font-size:5pt;' }}">Direcci&oacute;n:</td>
                <td style="{{ $bloques['info_valor']['css'] ?? 'font-size:5pt;' }}">{{ $clienteDireccion }}</td>
            </tr>
            @endif
            @if(!empty($clienteTelefono))
            <tr>
                <td style="text-transform:uppercase; {{ $bloques['info_label']['css'] ?? 'font-weight:bold;font-size:5pt;' }}">Tel&eacute;fono:</td>
                <td style="{{ $bloques['info_valor']['css'] ?? 'font-size:5pt;' }}">{{ $clienteTelefono }}</td>
            </tr>
            @endif
        </table>
    </div>

    <div class="separator"></div>

    {{-- Metodos de pago --}}
    @if(count($metodosPago) > 0)
    <div style="padding: 2px 0 4px;">
        <div style="margin-bottom: 2px; {{ $bloques['info_label']['css'] ?? 'font-weight:bold;font-size:6pt;' }}">M&eacute;todos de Pago:</div>
        <table>
            @foreach($metodosPago as $mp)
            <tr>
                <td style="{{ $bloques['tabla_fila']['css'] ?? 'font-size:6pt;' }}">{{ $mp['nombre'] }}</td>
                <td style="text-align: right; {{ $bloques['tabla_fila']['css'] ?? 'font-size:6pt;' }}">{{ number_format($mp['monto'], 2) }}</td>
            </tr>
            @if(isset($mp['sobrecargo_aplicado']) && $mp['sobrecargo_aplicado'] > 0 && !empty($mp['mostrar_sobrecargo']))
            <tr>
                <td style="padding-left: 6px; color:#666; {{ $bloques['tabla_fila']['css'] ?? 'font-size:5pt;' }}">+ Sobrecargo ({{ number_format($mp['sobrecargo_aplicado'] / $mp['monto'] * 100, 1) }}%)</td>
                <td style="text-align: right; color:#666; {{ $bloques['tabla_fila']['css'] ?? 'font-size:5pt;' }}">{{ number_format($mp['sobrecargo_aplicado'], 2) }}</td>
            </tr>
            @endif
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
                    <th style="text-align:left; width:40%; {{ $bloques['tabla_header']['css'] ?? 'font-weight:bold;font-size:6pt;' }}">Descripci&oacute;n</th>
                    <th style="text-align:center; width:10%; {{ $bloques['tabla_header']['css'] ?? 'font-weight:bold;font-size:6pt;' }}">Cant.</th>
                    <th style="text-align:center; width:10%; {{ $bloques['tabla_header']['css'] ?? 'font-weight:bold;font-size:6pt;' }}">Unid.</th>
                    <th style="text-align:right; width:15%; {{ $bloques['tabla_header']['css'] ?? 'font-weight:bold;font-size:6pt;' }}">P.U.</th>
                    <th style="text-align:right; width:15%; {{ $bloques['tabla_header']['css'] ?? 'font-weight:bold;font-size:6pt;' }}">Subt.</th>
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
                <tr style="border-bottom: 1px solid #000;{{ !empty($p['es_gratis']) ? ' background-color: #fff3cd;' : ($i % 2 !== 0 ? ' background-color: #f9f9f9;' : '') }}">
                    <td style="padding:3px 0;{{ !empty($p['paquete_id']) ? ' padding-left:6px;' : '' }} {{ $bloques['tabla_fila']['css'] ?? 'font-size:6pt;' }}">
                        {{ $p['nombre'] }}
                        @if(!empty($p['es_gratis']))
                            <span style="display:inline-block; background:#000; color:#fff; padding:1px 4px; border-radius:2px; font-size:5pt; font-weight:bold; margin-left:2px;">GRATIS</span>
                        @endif
                    </td>
                    <td style="padding:3px 0; text-align:center; {{ $bloques['tabla_fila']['css'] ?? 'font-size:6pt;' }}">{{ number_format($p['cantidad'], 0) }}</td>
                    <td style="padding:3px 0; text-align:center; {{ $bloques['tabla_fila']['css'] ?? 'font-size:6pt;' }}">{{ $p['unidad'] }}</td>
                    <td style="padding:3px 0; text-align:right; {{ $bloques['tabla_fila']['css'] ?? 'font-size:6pt;' }}">{{ !empty($p['es_gratis']) ? '—' : number_format($p['precio'], 2) }}</td>
                    <td style="padding:3px 0; text-align:right; {{ $bloques['tabla_fila']['css'] ?? 'font-size:6pt;' }}">{{ !empty($p['es_gratis']) ? '0.00' : number_format($p['subtotal'], 2) }}</td>
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
                <td style="{{ $bloques['total_label']['css'] ?? 'font-weight:bold;font-size:7pt;' }}">TOTAL DESCUENTO</td>
                <td style="text-align:right; {{ $bloques['total_valor']['css'] ?? 'font-size:7pt;' }}">{{ number_format($calculos['total_descuento'], 2) }}</td>
            </tr>
            @endif
            <tr style="border-bottom: 1px solid #000;">
                <td style="{{ $bloques['total_label']['css'] ?? 'font-weight:bold;font-size:7pt;' }}">OP.GRAVADA</td>
                <td style="text-align:right; {{ $bloques['total_valor']['css'] ?? 'font-size:7pt;' }}">{{ number_format($calculos['subtotal'], 2) }}</td>
            </tr>
            <tr style="border-bottom: 1px solid #000;">
                <td style="{{ $bloques['total_label']['css'] ?? 'font-weight:bold;font-size:7pt;' }}">IGV 18%</td>
                <td style="text-align:right; {{ $bloques['total_valor']['css'] ?? 'font-size:7pt;' }}">{{ number_format($calculos['igv'], 2) }}</td>
            </tr>
            @if(!empty($valesDescuento ?? []))
                @foreach($valesDescuento as $vd)
                <tr style="border-bottom: 1px solid #000;">
                    <td style="{{ $bloques['total_label']['css'] ?? 'font-weight:bold;font-size:7pt;' }}">
                        DSCTO ({{ $vd['beneficio'] }})<br>
                        <span style="font-size:5pt; font-weight:normal;">{{ $vd['nombre'] }}</span>
                    </td>
                    <td style="text-align:right; {{ $bloques['total_valor']['css'] ?? 'font-size:7pt;' }}">-{{ number_format($vd['monto'], 2) }}</td>
                </tr>
                @endforeach
            @endif
            @if(($sobrecargoVisible ?? $calculos['sobrecargo'] ?? 0) > 0)
            <tr style="border-bottom: 1px solid #000;">
                <td style="{{ $bloques['total_label']['css'] ?? 'font-weight:bold;font-size:7pt;' }}">SOBRECARGO</td>
                <td style="text-align:right; {{ $bloques['total_valor']['css'] ?? 'font-size:7pt;' }}">{{ number_format($sobrecargoVisible ?? $calculos['sobrecargo'], 2) }}</td>
            </tr>
            @endif
            <tr>
                <td style="{{ $bloques['total_label']['css'] ?? 'font-weight:bold;font-size:7pt;' }}">TOTAL</td>
                <td style="text-align:right; font-weight:bold; {{ $bloques['total_valor']['css'] ?? 'font-size:7pt;' }}">{{ number_format($calculos['total'], 2) }}</td>
            </tr>
        </table>
    </div>

    {{-- Total en letras --}}
    <div style="margin-top: 4px; {{ $bloques['son']['css'] ?? 'font-size:7pt;' }}">
        {{ $son }} {{ $moneda ?? 'SOLES' }}
    </div>

    {{-- Observaciones --}}
    <div style="margin-top: 4px;">
        <div style="{{ $bloques['obs_label']['css'] ?? 'font-weight:bold;font-size:7pt;' }}">Observaciones:</div>
        <div style="{{ $bloques['obs_valor']['css'] ?? 'font-size:7pt;' }}">{{ $observaciones }}</div>
    </div>

    {{-- Enlace consulta documento --}}
    @if(isset($consultaUrl))
    <div class="separator"></div>
    <div style="margin-top: 4px; text-align: center;">
        <div style="{{ $bloques['consulta_leyenda']['css'] ?? 'font-size:6pt;color:#666;text-align:center;' }}">{{ $msg['leyenda_consulta'] ?? 'Consulte su documento en:' }}</div>
        <div style="word-break:break-all; {{ $bloques['consulta_url']['css'] ?? 'font-size:6pt;font-weight:bold;color:#333;text-align:center;' }}">
            {{ $consultaUrl }}
        </div>
    </div>
    @endif

    <div class="separator"></div>
    <div style="margin-top: 2px; text-align: center;">
        <div style="{{ $bloques['despedida_footer']['css'] ?? 'font-size:7pt;font-weight:bold;text-align:center;' }}">{!! ($plantilla->despedida_activo ?? false) && !empty($plantilla->mensaje_despedida) ? $plantilla->mensaje_despedida : 'GRACIAS POR SU PREFERENCIA!' !!}</div>
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