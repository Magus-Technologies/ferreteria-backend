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
        {{ $tipoOperacion }}<br>
        {{ $numeroDocumento }}
    </div>

    <div class="separator"></div>

    {{-- Info del prestamo --}}
    <div style="padding: 2px 0 6px;">
        <table>
            <tr>
                <td class="label" style="width: 50%;">F. Emisi&oacute;n: <span class="value">{{ $fechaEmision }}</span></td>
                <td class="label" style="width: 50%;">Hora: <span class="value">{{ $hora }}</span></td>
            </tr>
            <tr>
                <td class="label">F. Vencimiento:</td>
                <td class="value">{{ $fechaVencimiento }}</td>
            </tr>
            <tr>
                <td class="label">Vendedor:</td>
                <td class="value">{{ $vendedor }}</td>
            </tr>
            <tr>
                <td class="label">Estado:</td>
                <td class="value">{{ $estado }}</td>
            </tr>
            <tr>
                <td class="label">Moneda:</td>
                <td class="value">{{ $monedaNombre }}</td>
            </tr>
            @if($tasaInteres)
            <tr>
                <td class="label">Tasa Inter&eacute;s:</td>
                <td class="value">{{ $tasaInteres }}</td>
            </tr>
            @endif
        </table>
    </div>

    <div class="separator"></div>

    {{-- Info de la entidad --}}
    <div style="padding: 2px 0 6px;">
        <table>
            <tr>
                <td class="label">{{ $esCliente ? 'Cliente:' : 'Proveedor:' }}</td>
                <td class="value">{{ $entidadNombre }}</td>
            </tr>
            <tr>
                <td class="label">{{ strlen($entidadDocumento) === 11 ? 'RUC:' : 'DNI:' }}</td>
                <td class="value">{{ $entidadDocumento }}</td>
            </tr>
            @if($entidadDireccion)
            <tr>
                <td class="label">Direcci&oacute;n:</td>
                <td class="value">{{ $entidadDireccion }}</td>
            </tr>
            @endif
        </table>
    </div>

    <div class="separator"></div>

    {{-- Tabla de productos --}}
    <div style="padding-top: 4px;">
        <table>
            <thead>
                <tr style="border-bottom: 1px solid #000;">
                    <th class="text-bold" style="font-size: 6pt; text-align: left; width: 40%;">Descripci&oacute;n</th>
                    <th class="text-bold" style="font-size: 6pt; text-align: left; width: 15%;">Cant.</th>
                    <th class="text-bold" style="font-size: 6pt; text-align: left; width: 15%;">Unid.</th>
                    <th class="text-bold" style="font-size: 6pt; text-align: left; width: 15%;">Costo</th>
                    <th class="text-bold" style="font-size: 6pt; text-align: left; width: 15%;">Importe</th>
                </tr>
            </thead>
            <tbody>
                @foreach($productos as $i => $p)
                <tr style="border-bottom: 1px solid #000;{{ $i % 2 !== 0 ? ' background-color: #f9f9f9;' : '' }}">
                    <td style="font-size: 6pt; padding: 3px 0;">{{ $p['nombre'] }}</td>
                    <td style="font-size: 6pt; padding: 3px 0;">{{ number_format($p['cantidad'], 0) }}</td>
                    <td style="font-size: 6pt; padding: 3px 0;">{{ $p['unidad'] }}</td>
                    <td style="font-size: 6pt; padding: 3px 0;">{{ number_format($p['costo'], 2) }}</td>
                    <td style="font-size: 6pt; padding: 3px 0;">{{ number_format($p['subtotal'], 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Totales --}}
    <div style="margin-top: 4px;">
        <table>
            <tr style="border-bottom: 1px solid #000;">
                <td class="text-bold" style="font-size: 7pt;">MONTO TOTAL</td>
                <td class="text-right text-bold" style="font-size: 7pt;">{{ $monedaSymbol }} {{ number_format($montoTotal, 2) }}</td>
            </tr>
            <tr style="border-bottom: 1px solid #000;">
                <td class="text-bold" style="font-size: 7pt;">MONTO PAGADO</td>
                <td class="text-right" style="font-size: 7pt;">{{ $monedaSymbol }} {{ number_format($montoPagado, 2) }}</td>
            </tr>
            <tr>
                <td class="text-bold" style="font-size: 7pt;">SALDO PENDIENTE</td>
                <td class="text-right text-bold" style="font-size: 7pt;">{{ $monedaSymbol }} {{ number_format($montoPendiente, 2) }}</td>
            </tr>
        </table>
    </div>

    {{-- Observaciones --}}
    <div style="font-size: 7pt; margin-top: 4px;">
        <span class="text-bold">Observaciones:</span><br>
        {{ $observaciones }}
    </div>

    @if($garantia)
    <div style="font-size: 7pt; margin-top: 2px;">
        <span class="text-bold">Garant&iacute;a:</span> {{ $garantia }}
    </div>
    @endif
</body>
</html>
