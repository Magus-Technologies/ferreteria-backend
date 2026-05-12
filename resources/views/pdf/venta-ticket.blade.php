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
    {{-- Header: Logo + Empresa --}}
    <div class="text-center" style="margin-bottom: 4px;">
        @if($logoPath)
            <img src="{{ $logoPath }}" style="max-height: 120px; max-width: 180px;" alt="Logo">
        @endif
        <div style="margin-top: 2px;">
            <div class="text-bold" style="font-size: 9pt;">{{ $empresa->razon_social }}</div>
            <div class="text-bold">R.U.C. {{ $empresa->ruc }}</div>
            <div>{{ $empresa->direccion }}</div>
            <div><span class="text-bold">Cel:</span> {{ $empresa->telefono }}</div>
            <div><span class="text-bold">Email:</span> {{ $empresa->email }}</div>
        </div>
    </div>

    <div class="separator"></div>

    {{-- Tipo documento y numero --}}
    <div class="text-center text-bold" style="font-size: 9pt; padding: 4px 0;">
        {{ $tipoDocumentoTitulo }}<br>
        {{ $numeroDocumento }}
    </div>

    <div class="separator"></div>

    {{-- Info de la venta --}}
    <div style="padding: 2px 0 6px;">
        <table>
            <tr>
                <td class="label">Forma Pago:</td>
                <td class="value">{{ $formaPago }}</td>
            </tr>
            <tr>
                <td class="label" style="width: 50%;">F. Emisi&oacute;n: <span class="value">{{ $fechaEmision }}</span></td>
                <td class="label" style="width: 50%;">Hora: <span class="value">{{ $hora }}</span></td>
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

    {{-- Info del cliente --}}
    <div style="padding: 2px 0 6px;">
        <table>
            <tr>
                <td class="label">{{ strlen($clienteDocumento) === 11 ? 'RUC:' : 'DNI:' }}</td>
                <td class="value">{{ $clienteDocumento }}</td>
            </tr>
            <tr>
                <td class="label">Cliente:</td>
                <td class="value">{{ $clienteNombre }}</td>
            </tr>
            @if($clienteDireccion)
            <tr>
                <td class="label">Direcci&oacute;n:</td>
                <td class="value">{{ $clienteDireccion }}</td>
            </tr>
            @endif
        </table>
    </div>

    <div class="separator"></div>

    {{-- Metodos de pago --}}
    @if(count($metodosPago) > 0)
    <div style="padding: 2px 0 4px;">
        <div class="text-bold" style="font-size: 6pt; margin-bottom: 2px;">M&eacute;todos de Pago:</div>
        <table>
            @foreach($metodosPago as $mp)
            <tr>
                <td style="font-size: 6pt;">{{ $mp['nombre'] }}</td>
                <td style="font-size: 6pt; text-align: right;">{{ number_format($mp['monto'], 2) }}</td>
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
                    <th class="text-bold" style="font-size: 6pt; text-align: left; width: 40%;">Descripci&oacute;n</th>
                    <th class="text-bold" style="font-size: 6pt; text-align: center; width: 10%;">Cant.</th>
                    <th class="text-bold" style="font-size: 6pt; text-align: center; width: 10%;">Unid.</th>
                    <th class="text-bold" style="font-size: 6pt; text-align: right; width: 15%;">P.U.</th>
                    <th class="text-bold" style="font-size: 6pt; text-align: right; width: 15%;">Subt.</th>
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
                    <td style="font-size: 6pt; padding: 3px 0;{{ !empty($p['paquete_id']) ? ' padding-left: 6px;' : '' }}">{{ $p['nombre'] }}</td>
                    <td style="font-size: 6pt; padding: 3px 0; text-align: center;">{{ number_format($p['cantidad'], 0) }}</td>
                    <td style="font-size: 6pt; padding: 3px 0; text-align: center;">{{ $p['unidad'] }}</td>
                    <td style="font-size: 6pt; padding: 3px 0; text-align: right;">{{ number_format($p['precio'], 2) }}</td>
                    <td style="font-size: 6pt; padding: 3px 0; text-align: right;">{{ number_format($p['subtotal'], 2) }}</td>
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
                <td class="text-bold" style="font-size: 7pt;">TOTAL DESCUENTO</td>
                <td class="text-right" style="font-size: 7pt;">{{ number_format($calculos['total_descuento'], 2) }}</td>
            </tr>
            @endif
            <tr style="border-bottom: 1px solid #000;">
                <td class="text-bold" style="font-size: 7pt;">OP.GRAVADA</td>
                <td class="text-right" style="font-size: 7pt;">{{ number_format($calculos['subtotal'], 2) }}</td>
            </tr>
            <tr style="border-bottom: 1px solid #000;">
                <td class="text-bold" style="font-size: 7pt;">IGV 18%</td>
                <td class="text-right" style="font-size: 7pt;">{{ number_format($calculos['igv'], 2) }}</td>
            </tr>
            <tr>
                <td class="text-bold" style="font-size: 7pt;">TOTAL</td>
                <td class="text-right text-bold" style="font-size: 7pt;">{{ number_format($calculos['total'], 2) }}</td>
            </tr>
        </table>
    </div>

    {{-- Total en letras --}}
    <div style="font-size: 7pt; margin-top: 4px;">
        {{ $son }} SOLES
    </div>

    {{-- Observaciones --}}
    <div style="font-size: 7pt; margin-top: 4px;">
        <span class="text-bold">Observaciones:</span><br>
        {{ $observaciones }}
    </div>

    {{-- Enlace consulta documento --}}
    @if(isset($consultaUrl))
    <div class="separator"></div>
    <div class="text-center" style="margin-top: 4px;">
        <div style="font-size: 6pt; color: #666;">Consulte su documento en:</div>
        <div style="font-size: 6pt; font-weight: bold; color: #333; word-break: break-all;">
            {{ $consultaUrl }}
        </div>
    </div>
    @endif

    <div class="separator"></div>
    <div class="text-center" style="margin-top: 2px;">
        <div style="font-size: 7pt; font-weight: bold;">GRACIAS POR SU PREFERENCIA!</div>
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
