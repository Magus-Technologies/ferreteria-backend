@extends('pdf.layout.document')

@section('styles')
    .bg-theme-light { background-color: #fffbeb; }
    .border-theme-box {
        border: 1.5px solid #059669;
        border-radius: 4px;
    }
    .bg-theme-os { background-color: #ecfdf5; }
    .signature-line {
        border-top: 1px solid #000;
        width: 100%;
        margin-top: 28px;
        margin-bottom: 6px;
    }
@endsection

@section('content')
    {{-- Header --}}
    <table style="width: 100%; margin-bottom: 10px;">
        <tr>
            @if(!empty($logoPath))
            <td style="width: 200px; vertical-align: middle; padding-right: 3px;">
                <img src="{{ $logoPath }}" style="width: 180px;" />
            </td>
            @endif
            <td style="vertical-align: middle; text-align: center; font-size: 7pt; line-height: 1.4; max-width: 150px;">
                <div style="font-size: 9pt; font-weight: bold; margin-bottom: 2px;">
                    {{ $empresa->razon_social }}
                </div>
                <div style="font-size: 7pt; color: #666;">Dirección: {{ $empresa->direccion }}</div>
                <div style="font-size: 7pt; color: #666;">Teléfono: {{ $empresa->telefono ?? $empresa->celular ?? '' }}</div>
                <div style="font-size: 7pt; color: #666;">Email: {{ $empresa->email ?? '' }}</div>
            </td>
            <td style="width: 280px; vertical-align: middle; text-align: right;">
                <div style="width: 280px; margin-left: auto; border: 2px solid #059669; border-radius: 8px; overflow: hidden; background-color: #ecfdf5;">
                    <div style="text-align: center; font-size: 11pt; font-weight: bold; background-color: #059669; padding: 6px 8px; border-radius: 4px; color: white;">
                        ORDEN DE SERVICIO
                    </div>
                    <div style="text-align: center; font-size: 13pt; font-weight: bold; padding: 6px 8px;">
                        {{ $requerimiento->codigo }}
                    </div>
                    <div style="text-align: center; font-size: 7pt; padding-bottom: 6px;">
                        LOG-F-03
                    </div>
                </div>
            </td>
        </tr>
    </table>

    {{-- Información General --}}
    <div style="font-size: 9pt; font-weight: bold; text-transform: uppercase; margin-bottom: 6px; padding-bottom: 4px; border-bottom: 1px solid #059669;">
        Información General
    </div>
    <table class="border-theme-box" style="width: 100%; font-size: 8pt; margin-bottom: 14px; padding: 6px 10px;">
        <tr>
            <td style="width: 20%; font-weight: bold; text-transform: uppercase; font-size: 7.5pt; padding: 2px;">NÚMERO:</td>
            <td style="width: 30%; font-size: 8pt; padding: 2px;">{{ $requerimiento->codigo }}</td>
            <td style="width: 20%; font-weight: bold; text-transform: uppercase; font-size: 7.5pt; padding: 2px;">ÁREA:</td>
            <td style="width: 30%; font-size: 8pt; padding: 2px;">{{ $requerimiento->cargo }}</td>
        </tr>
        <tr>
            <td style="font-weight: bold; text-transform: uppercase; font-size: 7.5pt; padding: 2px;">FECHA:</td>
            <td style="font-size: 8pt; padding: 2px;">{{ $fechaFormato }}</td>
            <td style="font-weight: bold; text-transform: uppercase; font-size: 7.5pt; padding: 2px;">PRIORIDAD:</td>
            <td style="font-size: 8pt; padding: 2px;">{{ $requerimiento->prioridad }}</td>
        </tr>
        <tr>
            <td style="font-weight: bold; text-transform: uppercase; font-size: 7.5pt; padding: 2px;">TIPO:</td>
            <td style="font-size: 8pt; padding: 2px;">Orden de Servicio</td>
            <td style="font-weight: bold; text-transform: uppercase; font-size: 7.5pt; padding: 2px;">ESTADO:</td>
            <td style="font-size: 8pt; padding: 2px;">{{ $requerimiento->estado }}</td>
        </tr>
    </table>

    {{-- Solicitante y Vehículo --}}
    <div style="font-size: 9pt; font-weight: bold; text-transform: uppercase; margin-bottom: 6px; padding-bottom: 4px; border-bottom: 1px solid #059669;">
        Solicitante y Vehículo
    </div>
    <table class="border-theme-box" style="width: 100%; font-size: 8pt; margin-bottom: 14px; padding: 6px 10px;">
        <tr>
            <td style="width: 20%; font-weight: bold; text-transform: uppercase; font-size: 7.5pt; padding: 2px;">SOLICITANTE:</td>
            <td style="width: 30%; font-size: 8pt; padding: 2px;">{{ $requerimiento->user->name ?? '—' }}</td>
            <td style="width: 20%; font-weight: bold; text-transform: uppercase; font-size: 7.5pt; padding: 2px;">VEHÍCULO:</td>
            <td style="width: 30%; font-size: 8pt; padding: 2px;">
                @if($requerimiento->vehiculo)
                    {{ $requerimiento->vehiculo->name }} ({{ $requerimiento->vehiculo->placa ?? 'N/A' }})
                @else
                    —
                @endif
            </td>
        </tr>
        <tr>
            <td style="font-weight: bold; text-transform: uppercase; font-size: 7.5pt; padding: 2px;">AFECTA CALENDARIO:</td>
            <td colspan="3" style="font-size: 8pt; padding: 2px;">
                {{ $requerimiento->afecta_calendario ? 'Sí' : 'No' }}
            </td>
        </tr>
    </table>

    {{-- Tabla de Servicios --}}
    <div style="font-size: 9pt; font-weight: bold; text-transform: uppercase; margin-bottom: 6px; padding-bottom: 4px; border-bottom: 1px solid #059669;">
        Servicios Requeridos
    </div>
    <table style="width: 100%; border: 1.5px solid #059669; border-radius: 4px; overflow: hidden; margin-bottom: 14px;">
        <tr style="background-color: #059669; color: #1f2937;">
            <td style="padding: 4px; font-size: 7.5pt; font-weight: bold; text-align: center; width: 15%;">TIPO</td>
            <td style="padding: 4px; font-size: 7.5pt; font-weight: bold; text-align: left; width: 25%;">DESCRIPCIÓN</td>
            <td style="padding: 4px; font-size: 7.5pt; font-weight: bold; text-align: left; width: 15%;">LUGAR</td>
            <td style="padding: 4px; font-size: 7.5pt; font-weight: bold; text-align: center; width: 12%;">HORARIO</td>
            <td style="padding: 4px; font-size: 7.5pt; font-weight: bold; text-align: center; width: 13%;">DURACIÓN</td>
            <td style="padding: 4px; font-size: 7.5pt; font-weight: bold; text-align: right; width: 20%;">PRESUPUESTO</td>
        </tr>
        @forelse($servicios as $i => $srv)
        <tr style="border-bottom: 1px solid #059669; background-color: {{ $i % 2 === 0 ? '#fafafa' : '#fff' }};">
            <td style="padding: 3px 4px; font-size: 7.5pt; text-align: center; font-weight: bold; color: #059669;">{{ $srv['tipo'] }}</td>
            <td style="padding: 3px 4px; font-size: 7.5pt;">{{ $srv['descripcion'] }}</td>
            <td style="padding: 3px 4px; font-size: 7.5pt;">{{ $srv['lugar'] }}</td>
            <td style="padding: 3px 4px; font-size: 7.5pt; text-align: center;">{{ $srv['horario'] }}</td>
            <td style="padding: 3px 4px; font-size: 7.5pt; text-align: center;">{{ $srv['duracion'] }}</td>
            <td style="padding: 3px 4px; font-size: 7.5pt; text-align: right; font-weight: bold; color: #059669;">{{ $srv['presupuesto'] }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="6" style="padding: 8px; text-align: center; font-style: italic; color: #666;">Sin servicios registrados</td>
        </tr>
        @endforelse
    </table>

    {{-- Observaciones --}}
    <table class="border-theme-box" style="width: 100%; margin-bottom: 14px; padding: 8px 10px; font-size: 8pt;">
        <tr>
            <td>
                <span style="font-weight: bold;">Observaciones:</span><br>
                {{ $requerimiento->observaciones ?: '—' }}
            </td>
        </tr>
    </table>

    {{-- Firmas --}}
    <table style="width: 100%; margin-top: 24px;">
        <tr>
            <td style="width: 33%; text-align: center; vertical-align: bottom;">
                <div class="signature-line"></div>
                <div style="font-size: 7.5pt; font-weight: bold; text-transform: uppercase;">Responsable del Área</div>
            </td>
            <td style="width: 33%; text-align: center; vertical-align: bottom;">
                <div class="signature-line"></div>
                <div style="font-size: 7.5pt; font-weight: bold; text-transform: uppercase;">Solicitante</div>
            </td>
            <td style="width: 33%; text-align: center; vertical-align: bottom;">
                <div class="signature-line"></div>
                <div style="font-size: 7.5pt; font-weight: bold; text-transform: uppercase;">Supervisor</div>
            </td>
        </tr>
    </table>

    {{-- Aprobaciones --}}
    <table style="width: 100%; margin-top: 16px; border-top: 2px solid #059669; padding-top: 10px;">
        <tr>
            <td style="width: 33%; text-align: center; font-size: 7pt; vertical-align: top;">
                <div style="font-weight: bold; margin-bottom: 3px; text-transform: uppercase; font-size: 7.5pt;">Elaborado Por:</div>
                <div>Área de Servicios</div>
            </td>
            <td style="width: 33%; text-align: center; font-size: 7pt; vertical-align: top; border-left: 1.5px solid #059669; padding-left: 8px;">
                <div style="font-weight: bold; margin-bottom: 3px; text-transform: uppercase; font-size: 7.5pt;">Revisado Por:</div>
                <div>Departamento de Logística y Operaciones</div>
            </td>
            <td style="width: 33%; text-align: center; font-size: 7pt; vertical-align: top; border-left: 1.5px solid #059669; padding-left: 8px;">
                <div style="font-weight: bold; margin-bottom: 3px; text-transform: uppercase; font-size: 7.5pt;">Aprobado por:</div>
                <div>Gerencia General</div>
            </td>
        </tr>
    </table>
@endsection