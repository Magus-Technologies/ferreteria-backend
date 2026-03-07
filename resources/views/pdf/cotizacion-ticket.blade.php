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
        PROFORMA ELECTR&Oacute;NICA<br>
        {{ $numeroDocumento }}
    </div>

    <div class="separator"></div>

    {{-- Info cliente --}}
    <div style="padding: 2px 0 6px;">
        <table>
            <tr>
                <td style="font-size: 5pt;">
                    <span class="text-bold">FECHA DE EMISI&Oacute;N:</span>
                    {{ \App\Services\Pdf\PdfService::formatFecha($cotizacion->fecha) }}
                </td>
                <td style="font-size: 5pt;">
                    <span class="text-bold">F. VENCIMIENTO:</span>
                    {{ \App\Services\Pdf\PdfService::formatFecha($cotizacion->fecha_vencimiento) }}
                </td>
            </tr>
        </table>
        <div style="font-size: 5pt; margin-top: 1px;">
            <span class="text-bold">{{ strlen($clienteDocumento) === 11 ? 'RUC:' : 'DNI:' }}</span>
            {{ $clienteDocumento }}
        </div>
        <div style="font-size: 5pt; margin-top: 1px;">
            <span class="text-bold">CLIENTE:</span>
            {{ $clienteNombre }}
        </div>
        @if($clienteDireccion)
        <div style="font-size: 5pt; margin-top: 1px;">
            <span class="text-bold">DIRECCI&Oacute;N:</span>
            {{ $clienteDireccion }}
        </div>
        @endif
        <div style="font-size: 5pt; margin-top: 1px;">
            <span class="text-bold">VENDEDOR:</span>
            {{ $vendedor }}
        </div>
    </div>

    <div class="separator"></div>

    {{-- Totales arriba de la tabla --}}
    <div style="padding: 2px 4px 4px;">
        <table>
            <tr style="border-bottom: 1px solid #ccc;">
                <td class="text-bold" style="font-size: 8pt; padding: 2px 0;">SUBTOTAL:</td>
                <td class="text-right" style="font-size: 8pt; padding: 2px 0;">S/ {{ number_format($calculos['subtotal'], 2) }}</td>
            </tr>
            <tr style="border-bottom: 1px solid #ccc;">
                <td class="text-bold" style="font-size: 8pt; padding: 2px 0;">T. DESCUENTO:</td>
                <td class="text-right" style="font-size: 8pt; padding: 2px 0;">S/ {{ number_format($calculos['total_descuento'], 2) }}</td>
            </tr>
            <tr style="background-color: #f0f0f0;">
                <td class="text-bold" style="font-size: 9pt; padding: 3px 2px;">TOTAL:</td>
                <td class="text-right text-bold" style="font-size: 9pt; padding: 3px 2px;">S/ {{ number_format($calculos['total'], 2) }}</td>
            </tr>
        </table>
    </div>

    {{-- Tabla de productos --}}
    <div style="padding-top: 4px;">
        <table>
            <thead>
                <tr style="border-bottom: 1px solid #000;">
                    <th class="text-bold" style="font-size: 6pt; text-align: left; width: 55%;">Descripci&oacute;n</th>
                    <th class="text-bold" style="font-size: 6pt; text-align: left; width: 15%;">Cant.</th>
                    <th class="text-bold" style="font-size: 6pt; text-align: left; width: 15%;">P.U.</th>
                    <th class="text-bold" style="font-size: 6pt; text-align: left; width: 15%;">Subt.</th>
                </tr>
            </thead>
            <tbody>
                @foreach($productos as $p)
                <tr style="border-bottom: 1px solid #000;">
                    <td style="font-size: 6pt; padding: 3px 0;">{{ $p['nombre'] }}</td>
                    <td style="font-size: 6pt; padding: 3px 0;">{{ number_format($p['cantidad'], 0) }}</td>
                    <td style="font-size: 6pt; padding: 3px 0;">{{ number_format($p['precio'], 2) }}</td>
                    <td style="font-size: 6pt; padding: 3px 0;">{{ number_format($p['subtotal'], 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Total final --}}
    <div style="margin-top: 4px;">
        <table>
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
        -
    </div>
</body>
</html>
