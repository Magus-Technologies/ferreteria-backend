<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Ticket Entrega #{{ $entrega->id }}</title>
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
        table { border-collapse: collapse; width: 100%; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-bold { font-weight: bold; }
        .separator { border-top: 1px dashed #000; margin: 4px 0; }
        .section-title {
            font-size: 7pt;
            font-weight: bold;
            text-align: center;
            margin-bottom: 2px;
            padding-top: 4px;
            border-top: 1px dashed #000;
        }
        .label { font-weight: bold; text-transform: uppercase; font-size: 5pt; }
        .value { font-size: 5pt; }
    </style>
</head>
<body>
    {{-- Header --}}
    <div class="text-center" style="margin-bottom: 4px;">
        @if(!empty($logoPath))
            <img src="{{ $logoPath }}" style="max-height: 120px; max-width: 180px;" alt="Logo">
        @endif
        <div style="margin-top: 2px;">
            <div class="text-bold" style="font-size: 9pt;">{{ $empresa->razon_social ?? '' }}</div>
            <div class="text-bold">R.U.C. {{ $empresa->ruc ?? '' }}</div>
            <div>{{ $empresa->direccion ?? '' }}</div>
            @if($empresa->telefono ?? $empresa->celular ?? null)
                <div><span class="text-bold">Cel:</span> {{ $empresa->telefono ?? $empresa->celular }}</div>
            @endif
            @if($empresa->email ?? null)
                <div><span class="text-bold">Email:</span> {{ $empresa->email }}</div>
            @endif
        </div>
    </div>

    <div class="separator"></div>

    {{-- Título adaptable: si la entrega aún no ocurrió ('pe' o 'ec'), el PDF
         es un "Vale de Recojo" (papel que el cliente lleva al almacén).
         Si ya fue entregada o cancelada es el "Ticket de Entrega" formal. --}}
    @php
        $tituloPdf = match($entrega->estado_entrega) {
            'pe' => 'VALE DE RECOJO',
            'ec' => 'ENTREGA EN CAMINO',
            'en' => 'TICKET DE ENTREGA',
            'ca' => 'ENTREGA CANCELADA',
            default => 'TICKET DE ENTREGA',
        };
    @endphp
    <div class="text-center text-bold" style="font-size: 9pt; padding: 4px 0;">
        {{ $tituloPdf }}<br>
        <span style="font-size: 7pt;">Venta: {{ $nroVenta }}</span>
    </div>

    <div class="separator"></div>

    {{-- Info en 2 columnas --}}
    <div style="padding: 2px 0 6px;">
        <table>
            <tr>
                <td style="width: 50%; vertical-align: top; padding-right: 4px;">
                    <table>
                        <tr><td class="label">FECHA ENTREGA:</td></tr>
                        <tr><td class="value">{{ $entrega->fecha_entrega ? \Carbon\Carbon::parse($entrega->fecha_entrega)->format('d/m/Y') : '-' }}</td></tr>
                        <tr><td style="height: 3px;"></td></tr>
                        <tr><td class="label">TIPO ENTREGA:</td></tr>
                        <tr><td class="value">{{ $tipoEntregaLabel }}</td></tr>
                        <tr><td style="height: 3px;"></td></tr>
                        <tr><td class="label">DESPACHADOR:</td></tr>
                        <tr><td class="value">{{ $entrega->despachador->name ?? $entrega->user->name ?? '-' }}</td></tr>
                    </table>
                </td>
                <td style="width: 50%; vertical-align: top; padding-left: 4px;">
                    <table>
                        @if($entrega->fecha_programada)
                        <tr><td class="label">FECHA PROGRAMADA:</td></tr>
                        <tr><td class="value">{{ \Carbon\Carbon::parse($entrega->fecha_programada)->format('d/m/Y') }}</td></tr>
                        <tr><td style="height: 3px;"></td></tr>
                        @endif
                        <tr><td class="label">TIPO DESPACHO:</td></tr>
                        <tr><td class="value">{{ $tipoDespachoLabel }}</td></tr>
                        <tr><td style="height: 3px;"></td></tr>
                        @if($entrega->hora_inicio || $entrega->hora_fin)
                        <tr><td class="label">HORARIO:</td></tr>
                        <tr><td class="value">{{ $entrega->hora_inicio ?? '' }} - {{ $entrega->hora_fin ?? '' }}</td></tr>
                        @endif
                    </table>
                </td>
            </tr>
        </table>
    </div>

    {{-- Datos del Cliente --}}
    <div class="section-title">DATOS DEL CLIENTE</div>
    <table style="font-size: 6pt; padding: 2px 0;">
        <tr>
            <td class="label" style="width: 25%;">CLIENTE:</td>
            <td class="value">
                @if($cliente)
                    {{ $cliente->razon_social ?? ($cliente->nombres . ' ' . ($cliente->apellidos ?? '')) }}
                @else
                    -
                @endif
            </td>
        </tr>
        <tr>
            <td class="label">DOCUMENTO:</td>
            <td class="value">{{ $cliente->numero_documento ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">TELÉFONO:</td>
            <td class="value">{{ $cliente->telefono ?? $cliente->celular ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">DIRECCIÓN:</td>
            <td class="value">{{ $entrega->direccion_entrega ?? '-' }}</td>
        </tr>
    </table>

    {{-- Tabla productos — muestra entregado vs pendiente según estado --}}
    <div class="section-title">PRODUCTOS</div>
    <table style="font-size: 5pt;">
        <tr style="border-bottom: 1px solid #000;">
            <td class="text-bold" style="width: 30px;">Cód.</td>
            <td class="text-bold">Producto</td>
            <td class="text-bold text-center" style="width: 30px;">Unidad</td>
            <td class="text-bold text-center" style="width: 25px;">Entreg.</td>
            <td class="text-bold text-center" style="width: 25px;">Pend.</td>
        </tr>
        @foreach($productos as $i => $p)
        <tr style="background-color: {{ $i % 2 === 0 ? '#fff' : '#f9f9f9' }};">
            <td>{{ $p['codigo'] }}</td>
            <td>{{ $p['nombre'] }}</td>
            <td class="text-center">{{ $p['unidad'] }}</td>
            <td class="text-center">{{ number_format($p['entregado'], 0) }}</td>
            <td class="text-center">{{ number_format($p['pendiente'], 0) }}</td>
        </tr>
        @endforeach
    </table>

    {{-- Total items --}}
    <table style="margin-top: 4px;">
        <tr style="border-top: 2px solid #000; background-color: #f0f0f0;">
            <td style="padding: 4px; font-size: 8pt; font-weight: bold;">TOTAL ITEMS</td>
            <td style="padding: 4px; font-size: 8pt; font-weight: bold; text-align: right;">{{ count($productos) }}</td>
        </tr>
    </table>

    {{-- Observaciones --}}
    @if($entrega->observaciones)
    <div style="margin-top: 4px; font-size: 6pt;">
        <span class="text-bold">Observaciones:</span><br>
        {{ $entrega->observaciones }}
    </div>
    @endif

    {{-- Firma --}}
    <div style="margin-top: 20px; padding-top: 2px;">
        <table>
            <tr>
                <td style="width: 50%; text-align: center; padding-top: 20px; border-top: 1px solid #000; font-size: 5pt;">
                    Firma del Despachador
                </td>
                <td style="width: 10%;"></td>
                <td style="width: 50%; text-align: center; padding-top: 20px; border-top: 1px solid #000; font-size: 5pt;">
                    Firma del Cliente
                </td>
            </tr>
        </table>
    </div>

    {{-- Footer --}}
    <div style="margin-top: 6px; padding-top: 4px; border-top: 1px dashed #000;">
        <div style="text-align: center; font-size: 4pt; color: #999;">
            Documento generado el {{ now()->format('d/m/Y H:i:s') }}
        </div>
    </div>
</body>
</html>
