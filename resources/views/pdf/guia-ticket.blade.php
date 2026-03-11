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
        GUIA DE REMISION<br>
        {{ $numeroDocumento }}
    </div>

    <div class="separator"></div>

    {{-- Info de la guia --}}
    <div style="padding: 2px 0 6px;">
        <table>
            <tr>
                <td class="label" style="width: 50%;">F. Emisi&oacute;n: <span class="value">{{ $fechaEmision }}</span></td>
                <td class="label" style="width: 50%;">F. Traslado: <span class="value">{{ $fechaTraslado }}</span></td>
            </tr>
            <tr>
                <td class="label">Motivo:</td>
                <td class="value">{{ $motivoTraslado }}</td>
            </tr>
            <tr>
                <td class="label">Modalidad:</td>
                <td class="value">{{ $modalidad }}</td>
            </tr>
            <tr>
                <td class="label">P. Partida:</td>
                <td class="value">{{ $puntoPartida }}</td>
            </tr>
            <tr>
                <td class="label">P. Llegada:</td>
                <td class="value">{{ $puntoLlegada }}</td>
            </tr>
            <tr>
                <td class="label" style="width: 50%;">Vehiculo: <span class="value">{{ $vehiculoPlaca }}</span></td>
                <td class="label" style="width: 50%;">Chofer: <span class="value">{{ $choferDni }}</span></td>
            </tr>
            @if($choferNombre !== '-')
            <tr>
                <td class="label">Nombre:</td>
                <td class="value">{{ $choferNombre }}</td>
            </tr>
            @endif
        </table>
    </div>

    <div class="separator"></div>

    {{-- Destinatario --}}
    <div style="padding: 2px 0 6px;">
        <table>
            <tr>
                <td class="label">{{ strlen($clienteDocumento) === 11 ? 'RUC:' : 'DNI:' }}</td>
                <td class="value">{{ $clienteDocumento }}</td>
            </tr>
            <tr>
                <td class="label">Destinatario:</td>
                <td class="value">{{ $clienteNombre }}</td>
            </tr>
        </table>
    </div>

    <div class="separator"></div>

    {{-- Tabla de detalles --}}
    <div style="padding-top: 4px;">
        <table>
            <thead>
                <tr style="border-bottom: 1px solid #000;">
                    <th class="text-bold" style="font-size: 6pt; text-align: left; width: 40%;">Descripci&oacute;n</th>
                    <th class="text-bold" style="font-size: 6pt; text-align: left; width: 15%;">Cant.</th>
                    <th class="text-bold" style="font-size: 6pt; text-align: left; width: 20%;">Unid.</th>
                    <th class="text-bold" style="font-size: 6pt; text-align: left; width: 25%;">Peso(kg)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($detalles as $i => $d)
                <tr style="border-bottom: 1px solid #000;{{ $i % 2 !== 0 ? ' background-color: #f9f9f9;' : '' }}">
                    <td style="font-size: 6pt; padding: 3px 0;">{{ $d['nombre'] }}</td>
                    <td style="font-size: 6pt; padding: 3px 0;">{{ number_format($d['cantidad'], 0) }}</td>
                    <td style="font-size: 6pt; padding: 3px 0;">{{ $d['unidad'] }}</td>
                    <td style="font-size: 6pt; padding: 3px 0;">{{ $d['peso'] > 0 ? number_format($d['peso'], 2) : '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Peso total --}}
    <div style="margin-top: 4px;">
        <table>
            <tr>
                <td class="text-bold" style="font-size: 7pt;">PESO TOTAL</td>
                <td class="text-right text-bold" style="font-size: 7pt;">{{ number_format($pesoTotal, 2) }} KG</td>
            </tr>
        </table>
    </div>

    {{-- Observaciones --}}
    <div style="font-size: 7pt; margin-top: 4px;">
        <span class="text-bold">Observaciones:</span><br>
        {{ $observaciones }}
    </div>

    {{-- QR --}}
    @if($codigoQr)
    <div class="text-center" style="margin-top: 6px;">
        <img src="{{ $codigoQr }}" style="width: 60px; height: 60px;" alt="QR">
        <div style="font-size: 5pt; color: #666; margin-top: 2px;">
            Representacion impresa del comprobante electronico
        </div>
    </div>
    @endif
</body>
</html>
