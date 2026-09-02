<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $titulo ?? 'Orden de Servicio' }}</title>
    <style>
        @page {
            size: 80mm auto;
            margin: 3mm;
        }
        {!! $font_face_css ?? '' !!}
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
        @if(!empty($logoPath) && !($msg['ocultar_logo'] ?? false))
            <img src="{{ $logoPath }}" style="max-height: 120px; max-width: 180px;" alt="Logo">
        @endif
        <div style="margin-top: 2px;">
            <div style="font-weight:bold; font-size:9pt;">{{ $empresa->razon_social }}</div>
            <div style="font-weight:bold; font-size:7pt;">R.U.C. {{ $empresa->ruc }}</div>
            <div style="font-size:6pt;">{{ $empresa->direccion }}</div>
            @if(!empty($empresa->telefono ?? $empresa->celular))
                <div style="font-size:6pt;"><span class="text-bold">Cel:</span> {{ $empresa->telefono ?? $empresa->celular }}</div>
            @endif
            @if(!empty($empresa->email))
                <div style="font-size:6pt;"><span class="text-bold">Email:</span> {{ $empresa->email }}</div>
            @endif
        </div>
    </div>

    <div class="separator"></div>

    {{-- Tipo documento y número --}}
    <div style="padding: 4px 0; text-align: center;">
        <div style="font-weight:bold; font-size:9pt;">ORDEN DE SERVICIO</div>
        <div style="font-weight:bold; font-size:9pt;">{{ $requerimiento->codigo }}</div>
        <div style="font-size:6pt;">LOG-F-03</div>
    </div>

    <div class="separator"></div>

    {{-- Info general + Vehículo --}}
    <div style="padding: 2px 0 6px;">
        <table>
            <tr>
                <td style="width:40%; text-transform:uppercase; font-weight:bold; font-size:6pt;">Área:</td>
                <td style="font-size:6pt;">{{ $requerimiento->cargo }}</td>
            </tr>
            <tr>
                <td style="text-transform:uppercase; font-weight:bold; font-size:6pt;">Fecha:</td>
                <td style="font-size:6pt;">{{ $fechaFormato }}</td>
            </tr>
            <tr>
                <td style="text-transform:uppercase; font-weight:bold; font-size:6pt;">Prioridad:</td>
                <td style="font-size:6pt;">{{ $requerimiento->prioridad }}</td>
            </tr>
            <tr>
                <td style="text-transform:uppercase; font-weight:bold; font-size:6pt;">Estado:</td>
                <td style="font-size:6pt;">{{ $requerimiento->estado }}</td>
            </tr>
            <tr>
                <td style="text-transform:uppercase; font-weight:bold; font-size:6pt;">Solicitante:</td>
                <td style="font-size:6pt;">{{ $requerimiento->user->name ?? '—' }}</td>
            </tr>
            <tr>
                <td style="text-transform:uppercase; font-weight:bold; font-size:6pt;">Vehículo:</td>
                <td style="font-size:6pt;">
                    @if($requerimiento->vehiculo)
                        {{ $requerimiento->vehiculo->name ?? $requerimiento->vehiculo->placa ?? '—' }}
                        @if(!empty($requerimiento->vehiculo->placa) && !empty($requerimiento->vehiculo->name))
                            ({{ $requerimiento->vehiculo->placa }})
                        @endif
                    @else
                        —
                    @endif
                </td>
            </tr>
            <tr>
                <td style="text-transform:uppercase; font-weight:bold; font-size:6pt;">Afecta Calend.:</td>
                <td style="font-size:6pt;">{{ $requerimiento->afecta_calendario ? 'Sí' : 'No' }}</td>
            </tr>
        </table>
    </div>

    <div class="separator"></div>

    {{-- Tabla de servicios --}}
    <div style="padding-top: 4px;">
        <table>
            <thead>
                <tr>
                    <th colspan="3" style="text-align:left; font-weight:bold; font-size:6pt; border-bottom: 1px solid #000;">Descripción</th>
                </tr>
                <tr style="border-bottom: 1px solid #000;">
                    <th style="text-align:center; width:34%; font-weight:bold; font-size:6pt;">Tipo</th>
                    <th style="text-align:center; width:33%; font-weight:bold; font-size:6pt;">Dur.</th>
                    <th style="text-align:right; width:33%; font-weight:bold; font-size:6pt;">Presup.</th>
                </tr>
            </thead>
            <tbody>
                @forelse($servicios as $i => $srv)
                @php $bgFila = $i % 2 !== 0 ? ' background-color: #f9f9f9;' : ''; @endphp
                <tr style="{{ $bgFila }}">
                    <td colspan="3" style="padding:2px 0 0; font-weight:bold; font-size:6pt;">{{ $srv['descripcion'] }}</td>
                </tr>
                <tr style="border-bottom: 1px solid #000;{{ $bgFila }}">
                    <td style="padding:0 0 2px; text-align:center; font-size:6pt;">{{ $srv['tipo'] }}</td>
                    <td style="padding:0 0 2px; text-align:center; font-size:6pt;">{{ $srv['duracion'] ?? $srv['horario'] ?? '—' }}</td>
                    <td style="padding:0 0 2px; text-align:right; font-size:6pt;">{{ $srv['presupuesto'] }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="3" style="padding:4px; text-align:center; font-style:italic; color:#666; font-size:6pt;">Sin servicios registrados</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Observaciones --}}
    @if($requerimiento->observaciones)
    <div class="separator"></div>
    <div style="margin-top: 4px;">
        <div style="font-weight:bold; font-size:6pt; text-transform:uppercase;">Observaciones:</div>
        <div style="font-size:6pt;">{{ $requerimiento->observaciones }}</div>
    </div>
    @endif

    <div class="separator"></div>

    {{-- Firmas --}}
    <table style="margin-top: 12px;">
        <tr>
            <td style="width:33%; text-align:center; vertical-align:bottom; font-size:5pt;">
                <div style="border-top: 1px solid #000; margin-top: 18px; padding-top: 2px;">Responsable</div>
            </td>
            <td style="width:33%; text-align:center; vertical-align:bottom; font-size:5pt;">
                <div style="border-top: 1px solid #000; margin-top: 18px; padding-top: 2px;">Solicitante</div>
            </td>
            <td style="width:33%; text-align:center; vertical-align:bottom; font-size:5pt;">
                <div style="border-top: 1px solid #000; margin-top: 18px; padding-top: 2px;">Supervisor</div>
            </td>
        </tr>
    </table>
</body>
</html>
