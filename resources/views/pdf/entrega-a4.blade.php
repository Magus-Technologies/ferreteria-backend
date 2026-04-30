<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    @php
        $tituloPdf = match($entrega->estado_entrega) {
            'pe' => 'VALE DE RECOJO',
            'ec' => 'ENTREGA EN CAMINO',
            'en' => 'TICKET DE ENTREGA',
            'ca' => 'ENTREGA CANCELADA',
            default => 'TICKET DE ENTREGA',
        };
    @endphp
    <title>{{ $tituloPdf }} #{{ $entrega->id }}</title>
    <style>
        @page {
            size: A4;
            margin: 15mm 18mm;
        }
        * { box-sizing: border-box; }
        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 10pt;
            color: #1f2937;
            line-height: 1.45;
            margin: 0;
            padding: 0;
        }
        table { border-collapse: collapse; width: 100%; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-bold { font-weight: bold; }
        .muted { color: #6b7280; }
        .header {
            border-bottom: 2px solid #111827;
            padding-bottom: 8px;
            margin-bottom: 14px;
        }
        .empresa-name { font-size: 14pt; font-weight: bold; }
        .empresa-info { font-size: 9pt; color: #4b5563; line-height: 1.35; }
        .doc-box {
            border: 2px solid #111827;
            border-radius: 4px;
            padding: 8px 12px;
            text-align: center;
            min-width: 180px;
        }
        .doc-tipo {
            font-size: 13pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #111827;
        }
        .doc-num { font-size: 10pt; margin-top: 3px; }
        .section-title {
            font-size: 9pt;
            font-weight: bold;
            text-transform: uppercase;
            background: #f3f4f6;
            padding: 5px 8px;
            border-left: 3px solid #f59e0b;
            margin: 12px 0 6px;
            letter-spacing: 0.4px;
        }
        .field-row { padding: 3px 0; font-size: 10pt; }
        .field-label { font-weight: bold; color: #4b5563; min-width: 110px; display: inline-block; }
        .productos-table { font-size: 9.5pt; margin-top: 6px; }
        .productos-table th {
            background: #1f2937;
            color: #fff;
            padding: 6px 8px;
            text-align: left;
            font-weight: bold;
            font-size: 9pt;
            text-transform: uppercase;
        }
        .productos-table td {
            padding: 5px 8px;
            border-bottom: 1px solid #e5e7eb;
        }
        .productos-table tr:nth-child(even) td { background: #f9fafb; }
        .totals-box {
            margin-top: 6px;
            background: #f3f4f6;
            padding: 8px 12px;
            text-align: right;
            font-weight: bold;
            font-size: 10.5pt;
        }
        .firma-row { margin-top: 35px; }
        .firma-cell {
            text-align: center;
            border-top: 1px solid #111827;
            padding-top: 6px;
            font-size: 9pt;
            color: #4b5563;
        }
        .footer {
            margin-top: 18px;
            padding-top: 8px;
            border-top: 1px dashed #d1d5db;
            font-size: 8pt;
            color: #9ca3af;
            text-align: center;
        }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 8pt;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-pe { background: #ffedd5; color: #9a3412; }
        .badge-ec { background: #dbeafe; color: #1e40af; }
        .badge-en { background: #dcfce7; color: #166534; }
        .badge-ca { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    {{-- Header con logo + datos empresa + caja del documento --}}
    <table class="header">
        <tr>
            <td style="vertical-align: top; width: 65%;">
                @if(!empty($logoPath))
                    <img src="{{ $logoPath }}" style="max-height: 60px; max-width: 200px;" alt="Logo">
                @endif
                <div class="empresa-name" style="margin-top: 4px;">{{ $empresa->razon_social ?? '' }}</div>
                <div class="empresa-info">
                    R.U.C. {{ $empresa->ruc ?? '' }}<br>
                    {{ $empresa->direccion ?? '' }}
                    @if($empresa->telefono ?? $empresa->celular ?? null)
                        <br>Tel/Cel: {{ $empresa->telefono ?? $empresa->celular }}
                    @endif
                    @if($empresa->email ?? null)
                        <br>{{ $empresa->email }}
                    @endif
                </div>
            </td>
            <td style="vertical-align: top; width: 35%; text-align: right;">
                <div class="doc-box">
                    <div class="doc-tipo">{{ $tituloPdf }}</div>
                    <div class="doc-num muted">Venta: {{ $nroVenta }}</div>
                    <div class="doc-num muted">Entrega #{{ $entrega->id }}</div>
                    @php
                        $badgeClass = 'badge-' . $entrega->estado_entrega;
                        $estadoLabel = match($entrega->estado_entrega) {
                            'pe' => 'Pendiente',
                            'ec' => 'En Camino',
                            'en' => 'Entregado',
                            'ca' => 'Cancelado',
                            default => $entrega->estado_entrega,
                        };
                    @endphp
                    <div style="margin-top: 6px;">
                        <span class="badge {{ $badgeClass }}">{{ $estadoLabel }}</span>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    {{-- Datos generales en 2 columnas --}}
    <div class="section-title">Datos de la Entrega</div>
    <table>
        <tr>
            <td style="width: 50%; vertical-align: top; padding-right: 14px;">
                <div class="field-row">
                    <span class="field-label">Fecha entrega:</span>
                    {{ $entrega->fecha_entrega ? \Carbon\Carbon::parse($entrega->fecha_entrega)->format('d/m/Y H:i') : '-' }}
                </div>
                <div class="field-row">
                    <span class="field-label">Tipo entrega:</span>
                    {{ $tipoEntregaLabel }}
                </div>
                <div class="field-row">
                    <span class="field-label">Tipo despacho:</span>
                    {{ $tipoDespachoLabel }}
                </div>
                @if($entrega->fecha_programada)
                    <div class="field-row">
                        <span class="field-label">Fecha programada:</span>
                        {{ \Carbon\Carbon::parse($entrega->fecha_programada)->format('d/m/Y') }}
                    </div>
                @endif
                @if($entrega->hora_inicio || $entrega->hora_fin)
                    <div class="field-row">
                        <span class="field-label">Horario:</span>
                        {{ $entrega->hora_inicio ?? '?' }} — {{ $entrega->hora_fin ?? '?' }}
                    </div>
                @endif
            </td>
            <td style="width: 50%; vertical-align: top; padding-left: 14px;">
                <div class="field-row">
                    <span class="field-label">Almacén salida:</span>
                    {{ $entrega->almacenSalida->name ?? '-' }}
                </div>
                <div class="field-row">
                    <span class="field-label">Despachador:</span>
                    {{ $entrega->despachador->name ?? '-' }}
                </div>
                <div class="field-row">
                    <span class="field-label">Registró:</span>
                    {{ $entrega->user->name ?? '-' }}
                </div>
                @if($entrega->vehiculo)
                    <div class="field-row">
                        <span class="field-label">Vehículo:</span>
                        {{ $entrega->vehiculo->name ?? '' }}
                        @if($entrega->vehiculo->placa)
                            ({{ $entrega->vehiculo->placa }})
                        @endif
                    </div>
                @endif
            </td>
        </tr>
    </table>

    {{-- Datos del cliente --}}
    <div class="section-title">Datos del Cliente</div>
    <table>
        <tr>
            <td style="width: 50%; vertical-align: top; padding-right: 14px;">
                <div class="field-row">
                    <span class="field-label">Cliente:</span>
                    @if($cliente)
                        {{ $cliente->razon_social ?? trim(($cliente->nombres ?? '') . ' ' . ($cliente->apellidos ?? '')) ?: 'CLIENTES VARIOS' }}
                    @else
                        CLIENTES VARIOS
                    @endif
                </div>
                <div class="field-row">
                    <span class="field-label">Documento:</span>
                    {{ $cliente->numero_documento ?? '-' }}
                </div>
            </td>
            <td style="width: 50%; vertical-align: top; padding-left: 14px;">
                <div class="field-row">
                    <span class="field-label">Teléfono:</span>
                    {{ $cliente->telefono ?? $cliente->celular ?? '-' }}
                </div>
                <div class="field-row">
                    <span class="field-label">Dirección:</span>
                    {{ $entrega->direccion_entrega ?? '-' }}
                </div>
            </td>
        </tr>
    </table>

    {{-- Tabla de productos --}}
    <div class="section-title">Productos a Entregar</div>
    <table class="productos-table">
        <thead>
            <tr>
                <th style="width: 38px;">#</th>
                <th style="width: 90px;">Código</th>
                <th>Producto</th>
                <th style="width: 100px;">Unidad</th>
                <th style="width: 80px; text-align: center;">Cantidad</th>
            </tr>
        </thead>
        <tbody>
            @foreach($productos as $i => $p)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $p['codigo'] }}</td>
                    <td>{{ $p['nombre'] }}</td>
                    <td>{{ $p['unidad'] }}</td>
                    <td class="text-center">{{ number_format($p['cantidad'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals-box">
        Total ítems: {{ count($productos) }}
    </div>

    @if($entrega->observaciones)
        <div class="section-title">Observaciones</div>
        <div style="font-size: 10pt; padding: 4px 0;">{{ $entrega->observaciones }}</div>
    @endif

    {{-- Firmas --}}
    <table class="firma-row">
        <tr>
            <td style="width: 45%;" class="firma-cell">Firma del Despachador</td>
            <td style="width: 10%;"></td>
            <td style="width: 45%;" class="firma-cell">Firma del Cliente</td>
        </tr>
    </table>

    <div class="footer">
        Documento generado el {{ now()->format('d/m/Y H:i:s') }}
    </div>
</body>
</html>
