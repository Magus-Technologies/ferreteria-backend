<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Comprobantes de Cobro Masivos</title>
    <style>
        @page {
            margin: 0;
        }
        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 7pt;
            color: #000;
            line-height: 1.3;
            width: 80mm;
            margin: 0;
            padding: 0;
        }
        .ticket {
            width: 74mm;
            margin: 0 auto;
            padding: 3mm 0;
            page-break-inside: avoid;
            border-bottom: 2px dashed #000;
            padding-bottom: 5mm;
            margin-bottom: 5mm;
        }
        .ticket:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-bold { font-weight: bold; }
        .separator { border-top: 1px dashed #000; margin: 4px 0; }
        .separator-double { border-top: 2px solid #000; margin: 4px 0; }
        table { width: 100%; border-collapse: collapse; }
        .label { font-weight: bold; text-transform: uppercase; font-size: 6pt; }
        .value { font-size: 6pt; }
        .monto-grande { font-size: 14pt; font-weight: bold; }
    </style>
</head>
<body>
    @foreach($cobros as $item)
    <div class="ticket">
        {{-- Header: Logo + Empresa --}}
        <div class="text-center" style="margin-bottom: 4px;">
            @if($logoPath)
                <img src="{{ $logoPath }}" style="max-height: 100px; max-width: 150px;" alt="Logo">
            @endif
            <div style="margin-top: 2px;">
                <div class="text-bold" style="font-size: 9pt;">{{ $item['empresa']->razon_social }}</div>
                <div class="text-bold">R.U.C. {{ $item['empresa']->ruc }}</div>
                <div style="font-size: 6pt;">{{ $item['empresa']->direccion }}</div>
                <div style="font-size: 6pt;"><span class="text-bold">Cel:</span> {{ $item['empresa']->telefono }}</div>
            </div>
        </div>

        <div class="separator-double"></div>

        {{-- Titulo --}}
        <div class="text-center text-bold" style="font-size: 10pt; padding: 4px 0;">
            COMPROBANTE DE COBRO
        </div>

        <div class="separator"></div>

        {{-- Datos del cobro --}}
        <div style="padding: 2px 0 4px;">
            <table>
                <tr>
                    <td class="label" style="width: 35%;">Fecha Cobro:</td>
                    <td class="value">{{ \Carbon\Carbon::parse($item['cobro']->fecha)->format('d/m/Y') }}</td>
                </tr>
                <tr>
                    <td class="label">M&eacute;todo Pago:</td>
                    <td class="value">{{ $item['metodoPago'] }}</td>
                </tr>
                @if($item['cobro']->numero_operacion)
                <tr>
                    <td class="label">N&deg; Operaci&oacute;n:</td>
                    <td class="value">{{ $item['cobro']->numero_operacion }}</td>
                </tr>
                @endif
                <tr>
                    <td class="label">Registrado por:</td>
                    <td class="value">{{ $item['registradoPor'] }}</td>
                </tr>
            </table>
        </div>

        <div class="separator"></div>

        {{-- Monto cobrado (grande, destacado) --}}
        <div class="text-center" style="padding: 6px 0;">
            <div class="label" style="font-size: 6pt; margin-bottom: 2px;">MONTO COBRADO</div>
            <div class="monto-grande">S/. {{ number_format($item['cobro']->monto, 2) }}</div>
        </div>

        <div class="separator"></div>

        {{-- Referencia de la venta --}}
        <div style="padding: 2px 0 4px;">
            <div class="text-bold text-center" style="font-size: 7pt; margin-bottom: 3px;">REFERENCIA DE VENTA</div>
            <table>
                <tr>
                    <td class="label" style="width: 35%;">Documento:</td>
                    <td class="value">{{ $item['tipoDoc'] }} {{ $item['nroDocumento'] }}</td>
                </tr>
                <tr>
                    <td class="label">Cliente:</td>
                    <td class="value">{{ $item['clienteNombre'] }}</td>
                </tr>
                <tr>
                    <td class="label">Doc. Cliente:</td>
                    <td class="value">{{ $item['clienteDoc'] }}</td>
                </tr>
            </table>
        </div>

        <div class="separator"></div>

        {{-- Resumen de cuenta --}}
        <div style="padding: 2px 0 4px;">
            <table>
                <tr>
                    <td class="label" style="width: 55%;">Total de Venta:</td>
                    <td class="value text-right">S/. {{ $item['totalNeto'] }}</td>
                </tr>
                <tr>
                    <td class="label">Total Cobrado:</td>
                    <td class="value text-right">S/. {{ $item['totalCobrado'] }}</td>
                </tr>
                <tr>
                    <td class="text-bold" style="font-size: 7pt; padding-top: 2px;">SALDO PENDIENTE:</td>
                    <td class="text-bold text-right" style="font-size: 8pt; padding-top: 2px;">S/. {{ $item['saldoPendiente'] }}</td>
                </tr>
            </table>
        </div>

        <div class="separator-double"></div>

        {{-- Observaciones --}}
        @if($item['cobro']->observacion)
        <div style="padding: 2px 0;">
            <span class="label">Obs:</span>
            <span class="value">{{ $item['cobro']->observacion }}</span>
        </div>
        <div class="separator"></div>
        @endif

        {{-- Pie --}}
        <div class="text-center" style="padding: 4px 0; font-size: 5pt;">
            Comprobante de cobro masivo generado el {{ now()->format('d/m/Y H:i') }}
        </div>
    </div>
    @endforeach
</body>
</html>
