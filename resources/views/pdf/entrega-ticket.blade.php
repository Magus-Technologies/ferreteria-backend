<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Ticket Entrega #{{ $entrega->id }}</title>
    <style>
        {!! $font_face_css ?? '' !!}

        @page {
            size: 80mm auto;
            margin: 3mm;
        }
        body {
            font-family: "{{ $est['fuente'] ?? 'Helvetica' }}", Helvetica, Arial, sans-serif;
            font-size: {{ $est['font_pt'] ?? 7 }}pt;
            color: {{ $est['color_texto'] ?? '#000' }};
            line-height: 1.3;
            width: 74mm;
            margin: 0;
            padding: 0;
        }
        table { border-collapse: collapse; width: 100%; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-bold { font-weight: bold; }
        .separator { border-top: 1px dashed {{ $est['color_borde'] ?? '#000' }}; margin: 4px 0; }
        .section-title {
            font-weight: bold;
            text-align: center;
            margin-bottom: 2px;
            padding-top: 4px;
            border-top: 1px dashed {{ $est['color_borde'] ?? '#000' }};
        }
    </style>
</head>
<body>
    {{-- Header --}}
    <div class="text-center" style="margin-bottom: 4px;">
        @if(!empty($logoPath))
            <img src="{{ $logoPath }}" style="max-height: 120px; max-width: 180px;" alt="Logo">
        @endif
        <div style="margin-top: 2px;">
            <div style="{{ $bloques['empresa_razon']['css'] ?? '' }}">{{ $empresa->razon_social ?? '' }}</div>
            <div style="{{ $bloques['caja_ruc']['css'] ?? '' }}">R.U.C. {{ $empresa->ruc ?? '' }}</div>
            <div style="{{ $bloques['empresa_direccion']['css'] ?? '' }}">{{ $empresa->direccion ?? '' }}</div>
            @if($empresa->telefono ?? $empresa->celular ?? null)
                <div style="{{ $bloques['empresa_direccion']['css'] ?? '' }}"><span class="text-bold">Cel:</span> {{ $empresa->telefono ?? $empresa->celular }}</div>
            @endif
            @if($empresa->email ?? null)
                <div style="{{ $bloques['empresa_direccion']['css'] ?? '' }}"><span class="text-bold">Email:</span> {{ $empresa->email }}</div>
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
    <div style="padding: 4px 0;">
        <div style="{{ $bloques['caja_tipo']['css'] ?? '' }}">{{ $tituloPdf }}</div>
        <div style="{{ $bloques['caja_numero']['css'] ?? '' }}">Venta: {{ $nroVenta }}</div>
        @if(($entregasTotales ?? 1) > 1)
            <div style="{{ $bloques['caja_numero']['css'] ?? '' }} color: #ca3438;">Entrega {{ $entregaNumero }} de {{ $entregasTotales }}</div>
        @endif
    </div>

    <div class="separator"></div>

    {{-- Info en 2 columnas --}}
    <div style="padding: 2px 0 6px;">
        <table>
            <tr>
                <td style="width: 50%; vertical-align: top; padding-right: 4px;">
                    <table>
                        <tr><td style="{{ $bloques['entrega_info_label']['css'] ?? '' }}">FECHA ENTREGA:</td></tr>
                        <tr><td style="{{ $bloques['entrega_info_valor']['css'] ?? '' }}">{{ $entrega->fecha_entrega ? \Carbon\Carbon::parse($entrega->fecha_entrega)->format('d/m/Y') : '-' }}</td></tr>
                        <tr><td style="height: 3px;"></td></tr>
                        <tr><td style="{{ $bloques['entrega_info_label']['css'] ?? '' }}">TIPO ENTREGA:</td></tr>
                        <tr><td style="{{ $bloques['entrega_info_valor']['css'] ?? '' }}">{{ $tipoEntregaLabel }}</td></tr>
                        <tr><td style="height: 3px;"></td></tr>
                        <tr><td style="{{ $bloques['entrega_info_label']['css'] ?? '' }}">DESPACHADOR:</td></tr>
                        <tr><td style="{{ $bloques['entrega_info_valor']['css'] ?? '' }}">{{ $entrega->despachador->name ?? $entrega->user->name ?? '-' }}</td></tr>
                    </table>
                </td>
                <td style="width: 50%; vertical-align: top; padding-left: 4px;">
                    <table>
                        @if($entrega->fecha_programada)
                        <tr><td style="{{ $bloques['entrega_info_label']['css'] ?? '' }}">FECHA PROGRAMADA:</td></tr>
                        <tr><td style="{{ $bloques['entrega_info_valor']['css'] ?? '' }}">{{ \Carbon\Carbon::parse($entrega->fecha_programada)->format('d/m/Y') }}</td></tr>
                        <tr><td style="height: 3px;"></td></tr>
                        @endif
                        <tr><td style="{{ $bloques['entrega_info_label']['css'] ?? '' }}">TIPO DESPACHO:</td></tr>
                        <tr><td style="{{ $bloques['entrega_info_valor']['css'] ?? '' }}">{{ $tipoDespachoLabel }}</td></tr>
                        <tr><td style="height: 3px;"></td></tr>
                        @if($entrega->hora_inicio || $entrega->hora_fin)
                        <tr><td style="{{ $bloques['entrega_info_label']['css'] ?? '' }}">HORARIO:</td></tr>
                        <tr><td style="{{ $bloques['entrega_info_valor']['css'] ?? '' }}">{{ $entrega->hora_inicio ?? '' }} - {{ $entrega->hora_fin ?? '' }}</td></tr>
                        @endif
                    </table>
                </td>
            </tr>
        </table>
    </div>

    {{-- Datos del Cliente --}}
    <div class="section-title" style="{{ $bloques['obs_label']['css'] ?? '' }}">DATOS DEL CLIENTE</div>
    <table style="padding: 2px 0;">
        <tr>
            <td style="{{ $bloques['info_label']['css'] ?? '' }} width: 25%;">CLIENTE:</td>
            <td style="{{ $bloques['info_valor']['css'] ?? '' }}">
                @if($cliente)
                    {{ $cliente->razon_social ?? ($cliente->nombres . ' ' . ($cliente->apellidos ?? '')) }}
                @else
                    -
                @endif
            </td>
        </tr>
        <tr>
            <td style="{{ $bloques['info_label']['css'] ?? '' }}">DOCUMENTO:</td>
            <td style="{{ $bloques['info_valor']['css'] ?? '' }}">{{ $cliente->numero_documento ?? '-' }}</td>
        </tr>
        <tr>
            <td style="{{ $bloques['info_label']['css'] ?? '' }}">TEL&Eacute;FONO:</td>
            <td style="{{ $bloques['info_valor']['css'] ?? '' }}">{{ $cliente->telefono ?? $cliente->celular ?? '-' }}</td>
        </tr>
        <tr>
            <td style="{{ $bloques['info_label']['css'] ?? '' }}">DIRECCI&Oacute;N:</td>
            <td style="{{ $bloques['info_valor']['css'] ?? '' }}">{{ $entrega->direccion_entrega ?? '-' }}</td>
        </tr>
    </table>

    {{-- Tabla productos --}}
    <div class="section-title" style="{{ $bloques['obs_label']['css'] ?? '' }}">PRODUCTOS</div>
    <table>
        <tr style="border-bottom: 1px solid {{ $est['color_borde'] ?? '#000' }};">
            <td style="{{ $bloques['tabla_header']['css'] ?? '' }} text-align: left; width: 30px;">C&oacute;d.</td>
            <td style="{{ $bloques['tabla_header']['css'] ?? '' }} text-align: left;">Producto</td>
            <td style="{{ $bloques['tabla_header']['css'] ?? '' }} width: 30px;">Unidad</td>
            @if($mostrarRecibido)
            <td style="{{ $bloques['tabla_header']['css'] ?? '' }} width: 25px;">Recib.</td>
            @endif
            <td style="{{ $bloques['tabla_header']['css'] ?? '' }} width: 25px;">Entreg.</td>
            <td style="{{ $bloques['tabla_header']['css'] ?? '' }} width: 25px;">Pend.</td>
        </tr>
        @foreach($productosTabla as $i => $p)
        <tr style="background-color: {{ $i % 2 === 0 ? '#fff' : '#f9f9f9' }};">
            <td style="{{ $bloques['tabla_fila']['css'] ?? '' }}">{{ $p['codigo'] }}</td>
            <td style="{{ $bloques['tabla_fila']['css'] ?? '' }}">{{ $p['nombre'] }}</td>
            <td style="{{ $bloques['tabla_fila']['css'] ?? '' }} text-align: center;">{{ $p['unidad'] }}</td>
            @if($mostrarRecibido)
            <td style="{{ $bloques['tabla_fila']['css'] ?? '' }} text-align: center;">{{ number_format($p['recibido'], 0) }}</td>
            @endif
            <td style="{{ $bloques['tabla_fila']['css'] ?? '' }} text-align: center;">{{ number_format($p['entregado'], 0) }}</td>
            <td style="{{ $bloques['tabla_fila']['css'] ?? '' }} text-align: center;">{{ number_format($p['pendiente'], 0) }}</td>
        </tr>
        @endforeach
    </table>

    {{-- Total items --}}
    <table style="margin-top: 4px;">
        <tr style="border-top: 2px solid {{ $est['color_borde'] ?? '#000' }}; background-color: #f0f0f0;">
            <td style="{{ $bloques['total_label']['css'] ?? '' }} padding: 4px;">TOTAL ITEMS</td>
            <td style="{{ $bloques['total_valor']['css'] ?? '' }} padding: 4px;">{{ count($productosTabla) }}</td>
        </tr>
    </table>

    {{-- Observaciones --}}
    @if($entrega->observaciones)
    <div style="margin-top: 4px;">
        <div style="{{ $bloques['obs_label']['css'] ?? '' }}">{{ $msg['label_observaciones'] ?? 'OBSERVACIONES' }}</div>
        <div style="{{ $bloques['obs_valor']['css'] ?? '' }}">{{ $entrega->observaciones }}</div>
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
