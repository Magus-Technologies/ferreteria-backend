<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Comprobante de Pago</title>
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
        .separator-double { border-top: 2px solid #000; margin: 4px 0; }
        table { width: 100%; border-collapse: collapse; }
        .label { font-weight: bold; text-transform: uppercase; font-size: 5pt; }
        .value { font-size: 5pt; }
        .monto-grande { font-size: 14pt; font-weight: bold; }
    </style>
</head>
<body>
    <div class="text-center" style="margin-bottom: 4px;">
        @if($logoPath)
            <img src="{{ $logoPath }}" style="max-height: 120px; max-width: 180px;" alt="Logo">
        @endif
    </div>

    <div class="separator-double"></div>

    <div class="text-center text-bold" style="font-size: 10pt; padding: 4px 0;">
        COMPROBANTE DE PAGO
    </div>

    <div class="separator"></div>

    <div style="padding: 2px 0 4px;">
        <table>
            <tr>
                <td class="label" style="width: 35%;">Fecha Pago:</td>
                <td class="value">{{ \Carbon\Carbon::parse($pago->fecha)->format('d/m/Y') }}</td>
            </tr>
            <tr>
                <td class="label">M&eacute;todo Pago:</td>
                <td class="value">{{ $metodoPago }}</td>
            </tr>
            @if($pago->numero_operacion)
            <tr>
                <td class="label">N&deg; Operaci&oacute;n:</td>
                <td class="value">{{ $pago->numero_operacion }}</td>
            </tr>
            @endif
            @if($pago->numero_letra)
            <tr>
                <td class="label">N&deg; Letra:</td>
                <td class="value">{{ $pago->numero_letra }}</td>
            </tr>
            @endif
        </table>
    </div>

    <div class="separator"></div>

    <div class="text-center" style="padding: 6px 0;">
        <div class="label" style="font-size: 6pt; margin-bottom: 2px;">MONTO PAGADO</div>
        <div class="monto-grande">S/. {{ number_format($pago->monto, 2) }}</div>
    </div>

    <div class="separator"></div>

    <div style="padding: 2px 0 4px;">
        <div class="text-bold text-center" style="font-size: 7pt; margin-bottom: 3px;">REFERENCIA DE COMPRA</div>
        <table>
            <tr>
                <td class="label" style="width: 35%;">Documento:</td>
                <td class="value">{{ $tipoDoc }} {{ $nroDocumento }}</td>
            </tr>
            <tr>
                <td class="label">Proveedor:</td>
                <td class="value">{{ $proveedorNombre }}</td>
            </tr>
            <tr>
                <td class="label">RUC:</td>
                <td class="value">{{ $proveedorRuc }}</td>
            </tr>
        </table>
    </div>

    <div class="separator"></div>

    <div style="padding: 2px 0 4px;">
        <table>
            <tr>
                <td class="label" style="width: 55%;">Total de Compra:</td>
                <td class="value text-right">S/. {{ $totalNeto }}</td>
            </tr>
            <tr>
                <td class="label">Total Pagado:</td>
                <td class="value text-right">S/. {{ $totalPagado }}</td>
            </tr>
            <tr>
                <td class="text-bold" style="font-size: 7pt; padding-top: 2px;">SALDO PENDIENTE:</td>
                <td class="text-bold text-right" style="font-size: 8pt; padding-top: 2px;">S/. {{ $saldoPendiente }}</td>
            </tr>
        </table>
    </div>

    <div class="separator-double"></div>

    @if($pago->observacion)
    <div style="padding: 2px 0;">
        <span class="label">Obs:</span>
        <span class="value">{{ $pago->observacion }}</span>
    </div>
    <div class="separator"></div>
    @endif

    <div class="text-center" style="padding: 4px 0; font-size: 5pt;">
        Comprobante de pago generado el {{ now()->format('d/m/Y H:i') }}
    </div>
</body>
</html>
