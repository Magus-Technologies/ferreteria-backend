<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $titulo ?? 'Ticket' }}</title>
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
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-bold { font-weight: bold; }
        .separator { border-top: 1px dashed {{ $est['color_borde'] ?? '#000' }}; margin: 4px 0; }
        table { width: 100%; border-collapse: collapse; }
    </style>
</head>
<body>
    {{-- Header: Logo + Empresa --}}
    <div class="text-center" style="margin-bottom: 4px;">
        @if($logoPath && !($msg['ocultar_logo'] ?? false))
            <img src="{{ $logoPath }}" style="max-height: 120px; max-width: 180px;" alt="Logo">
        @endif
        <div style="margin-top: 2px;">
            <div style="{{ $bloques['empresa_razon']['css'] ?? '' }}">{{ $empresa->razon_social }}</div>
            <div style="{{ $bloques['caja_ruc']['css'] ?? '' }}">R.U.C. {{ $empresa->ruc }}</div>
            <div style="{{ $bloques['empresa_direccion']['css'] ?? '' }}">{{ $empresa->direccion }}</div>
            @if($empresa->telefono)
            <div style="{{ $bloques['empresa_direccion']['css'] ?? '' }}"><span class="text-bold">Cel:</span> {{ $empresa->telefono }}</div>
            @endif
            @if($empresa->email)
            <div style="{{ $bloques['empresa_direccion']['css'] ?? '' }}"><span class="text-bold">Email:</span> {{ $empresa->email }}</div>
            @endif
        </div>
    </div>

    <div class="separator"></div>

    {{-- Tipo documento y numero --}}
    <div style="padding: 4px 0;">
        <div style="{{ $bloques['caja_tipo']['css'] ?? '' }}">{{ $tipoGuiaTitulo ?? 'GUIA DE REMISION' }}</div>
        <div style="{{ $bloques['caja_numero']['css'] ?? '' }}">{{ $numeroDocumento }}</div>
    </div>

    <div class="separator"></div>

    {{-- Info de la guia --}}
    <div style="padding: 2px 0 6px;">
        <table>
            <tr>
                <td style="{{ $bloques['info_label']['css'] ?? '' }} width: 50%;">F. Emisi&oacute;n: <span style="{{ $bloques['info_valor']['css'] ?? '' }}">{{ $fechaEmision }}</span></td>
                <td style="{{ $bloques['info_label']['css'] ?? '' }} width: 50%;">F. Traslado: <span style="{{ $bloques['info_valor']['css'] ?? '' }}">{{ $fechaTraslado }}</span></td>
            </tr>
            <tr>
                <td style="{{ $bloques['info_label']['css'] ?? '' }}">Motivo:</td>
                <td style="{{ $bloques['info_valor']['css'] ?? '' }}">{{ $motivoTraslado }}</td>
            </tr>
            <tr>
                <td style="{{ $bloques['info_label']['css'] ?? '' }}">Modalidad:</td>
                <td style="{{ $bloques['info_valor']['css'] ?? '' }}">{{ $modalidad }}</td>
            </tr>
            <tr>
                <td style="{{ $bloques['info_label']['css'] ?? '' }}">P. Partida:</td>
                <td style="{{ $bloques['info_valor']['css'] ?? '' }}">{{ $puntoPartida }}</td>
            </tr>
            <tr>
                <td style="{{ $bloques['info_label']['css'] ?? '' }}">P. Llegada:</td>
                <td style="{{ $bloques['info_valor']['css'] ?? '' }}">{{ $puntoLlegada }}</td>
            </tr>
            <tr>
                <td style="{{ $bloques['info_label']['css'] ?? '' }} width: 50%;">Vehiculo: <span style="{{ $bloques['info_valor']['css'] ?? '' }}">{{ $vehiculoPlaca }}</span></td>
                <td style="{{ $bloques['info_label']['css'] ?? '' }} width: 50%;">Chofer: <span style="{{ $bloques['info_valor']['css'] ?? '' }}">{{ $choferDni }}</span></td>
            </tr>
            @if($choferNombre !== '-')
            <tr>
                <td style="{{ $bloques['info_label']['css'] ?? '' }}">Nombre:</td>
                <td style="{{ $bloques['info_valor']['css'] ?? '' }}">{{ $choferNombre }}</td>
            </tr>
            @endif
        </table>
    </div>

    <div class="separator"></div>

    {{-- Destinatario --}}
    <div style="padding: 2px 0 6px;">
        <table>
            <tr>
                <td style="{{ $bloques['info_label']['css'] ?? '' }}">{{ strlen($clienteDocumento ?? '') === 11 ? 'RUC:' : 'DNI:' }}</td>
                <td style="{{ $bloques['info_valor']['css'] ?? '' }}">{{ $clienteDocumento }}</td>
            </tr>
            <tr>
                <td style="{{ $bloques['info_label']['css'] ?? '' }}">Destinatario:</td>
                <td style="{{ $bloques['info_valor']['css'] ?? '' }}">{{ $clienteNombre }}</td>
            </tr>
        </table>
    </div>

    {{-- Comprador (motivos 03, 14) --}}
    @if(!empty($compradorNombre))
    <div style="padding: 2px 0 6px;">
        <table>
            <tr>
                <td style="{{ $bloques['info_label']['css'] ?? '' }}">{{ strlen($compradorDocumento ?? '') === 11 ? 'RUC:' : 'DNI:' }} Comprador:</td>
                <td style="{{ $bloques['info_valor']['css'] ?? '' }}">{{ $compradorDocumento }}</td>
            </tr>
            <tr>
                <td style="{{ $bloques['info_label']['css'] ?? '' }}">Comprador:</td>
                <td style="{{ $bloques['info_valor']['css'] ?? '' }}">{{ $compradorNombre }}</td>
            </tr>
        </table>
    </div>
    @endif

    {{-- Almacenes (motivo 08 - entre establecimientos) --}}
    @if(!empty($esEntreEstablecimientos))
    <div style="padding: 2px 0 6px;">
        <table>
            <tr>
                <td style="{{ $bloques['info_label']['css'] ?? '' }}">Almac&eacute;n Origen:</td>
                <td style="{{ $bloques['info_valor']['css'] ?? '' }}">{{ $almacenOrigen ?? '-' }}</td>
            </tr>
            <tr>
                <td style="{{ $bloques['info_label']['css'] ?? '' }}">Almac&eacute;n Destino:</td>
                <td style="{{ $bloques['info_valor']['css'] ?? '' }}">{{ $almacenDestino ?? '-' }}</td>
            </tr>
        </table>
    </div>
    @endif

    <div class="separator"></div>

    {{-- Tabla de detalles --}}
    <div style="padding-top: 4px;">
        <table>
            <thead>
                <tr style="border-bottom: 1px solid {{ $est['color_borde'] ?? '#000' }};">
                    <th style="{{ $bloques['tabla_header']['css'] ?? '' }} text-align: left; width: 40%;">Descripci&oacute;n</th>
                    <th style="{{ $bloques['tabla_header']['css'] ?? '' }} text-align: left; width: 15%;">Cant.</th>
                    <th style="{{ $bloques['tabla_header']['css'] ?? '' }} text-align: left; width: 20%;">Unid.</th>
                    <th style="{{ $bloques['tabla_header']['css'] ?? '' }} text-align: left; width: 25%;">Peso(kg)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($detalles as $i => $d)
                <tr style="border-bottom: 1px solid {{ $est['color_borde'] ?? '#000' }};{{ $i % 2 !== 0 ? ' background-color: #f9f9f9;' : '' }}">
                    <td style="{{ $bloques['tabla_fila']['css'] ?? '' }} padding: 3px 0;">{{ $d['nombre'] }}</td>
                    <td style="{{ $bloques['tabla_fila']['css'] ?? '' }} padding: 3px 0;">{{ \App\Helpers\Formato::cantidad($d['cantidad']) }}</td>
                    <td style="{{ $bloques['tabla_fila']['css'] ?? '' }} padding: 3px 0;">{{ $d['unidad'] }}</td>
                    <td style="{{ $bloques['tabla_fila']['css'] ?? '' }} padding: 3px 0;">{{ $d['peso'] > 0 ? number_format($d['peso'], 2) : '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Peso total --}}
    <div style="margin-top: 4px;">
        <table>
            <tr>
                <td style="{{ $bloques['total_label']['css'] ?? '' }}">PESO TOTAL</td>
                <td style="{{ $bloques['total_valor']['css'] ?? '' }}">{{ number_format($pesoTotal, 2) }} KG</td>
            </tr>
        </table>
    </div>

    {{-- Observaciones --}}
    <div style="margin-top: 4px;">
        @if(!empty($referencia))
        <div style="{{ $bloques['obs_label']['css'] ?? '' }}">Referencia:</div>
        <div style="{{ $bloques['obs_valor']['css'] ?? '' }} margin-bottom: 4px;">{{ $referencia }}</div>
        @endif
        <div style="{{ $bloques['obs_label']['css'] ?? '' }}">{{ $msg['label_observaciones'] ?? 'OBSERVACIONES' }}</div>
        <div style="{{ $bloques['obs_valor']['css'] ?? '' }}">{{ $observaciones }}</div>
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

    {{-- Enlace consulta documento --}}
    @if(isset($consultaUrl))
    <div class="separator" style="margin-top: 4px;"></div>
    <div class="text-center" style="margin-top: 4px;">
        <div style="font-size: 6pt; color: #666;">Consulte su documento en:</div>
        <div style="font-size: 6pt; font-weight: bold; color: #333; word-break: break-all;">
            {{ $consultaUrl }}
        </div>
    </div>
    @endif

    <div class="text-center" style="margin-top: 4px;">
        <div style="font-size: 7pt; font-weight: bold;">GRACIAS POR SU PREFERENCIA!</div>
    </div>
</body>
</html>
