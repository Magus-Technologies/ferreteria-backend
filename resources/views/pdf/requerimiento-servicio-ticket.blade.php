@extends('pdf.layout.document')

@section('styles')
    .bg-theme-light { background-color: #ecfdf5; }
    .border-theme-box {
        border: 1.5px solid #059669;
        border-radius: 4px;
    }
@endsection

@section('content')
    {{-- Header compacto --}}
    <table style="width: 100%; margin-bottom: 6px;">
        <tr>
            <td style="width: 60px; vertical-align: middle;">
                @if(!empty($logoPath))
                <img src="{{ $logoPath }}" style="width: 50px;" />
                @endif
            </td>
            <td style="vertical-align: middle;">
                <div style="font-size: 8pt; font-weight: bold;">{{ $empresa->razon_social }}</div>
                <div style="font-size: 6pt; color: #666;">{{ $empresa->telefono ?? '' }}</div>
            </td>
            <td style="width: 140px; vertical-align: middle; text-align: right;">
                <div style="border: 1.5px solid #059669; border-radius: 4px; overflow: hidden; background-color: #ecfdf5; text-align: center;">
                    <div style="font-size: 7pt; font-weight: bold; background-color: #059669; padding: 2px; color: white;">OS</div>
                    <div style="font-size: 9pt; font-weight: bold; padding: 2px;">{{ $requerimiento->codigo }}</div>
                </div>
            </td>
        </tr>
    </table>

    {{-- Información General --}}
    <table class="border-theme-box bg-theme-light" style="width: 100%; font-size: 7pt; margin-bottom: 8px; padding: 4px 6px;">
        <tr>
            <td style="font-weight: bold; text-transform: uppercase; font-size: 6.5pt; padding: 1px;">ÁREA:</td>
            <td style="font-size: 7pt; padding: 1px;">{{ $requerimiento->cargo }}</td>
            <td style="font-weight: bold; text-transform: uppercase; font-size: 6.5pt; padding: 1px;">FECHA:</td>
            <td style="font-size: 7pt; padding: 1px;">{{ $fechaFormato }}</td>
        </tr>
        <tr>
            <td style="font-weight: bold; text-transform: uppercase; font-size: 6.5pt; padding: 1px;">PRIORIDAD:</td>
            <td style="font-size: 7pt; padding: 1px;">{{ $requerimiento->prioridad }}</td>
            <td style="font-weight: bold; text-transform: uppercase; font-size: 6.5pt; padding: 1px;">VEHÍCULO:</td>
            <td style="font-size: 7pt; padding: 1px;">
                @if($requerimiento->vehiculo)
                    {{ $requerimiento->vehiculo->placa ?? '—' }}
                @else
                    —
                @endif
            </td>
        </tr>
        <tr>
            <td style="font-weight: bold; text-transform: uppercase; font-size: 6.5pt; padding: 1px;">SOLICITANTE:</td>
            <td colspan="3" style="font-size: 7pt; padding: 1px;">{{ $requerimiento->user->name ?? '—' }}</td>
        </tr>
    </table>

    {{-- Tabla de Servicios --}}
    <div style="font-size: 8pt; font-weight: bold; text-transform: uppercase; margin-bottom: 4px; padding-bottom: 2px; border-bottom: 1px solid #059669;">
        Servicios Requeridos
    </div>
    <table style="width: 100%; border: 1px solid #059669; border-radius: 3px; overflow: hidden; margin-bottom: 8px;">
        <tr style="background-color: #059669; color: white;">
            <td style="padding: 2px; font-size: 6pt; font-weight: bold; text-align: center; width: 20%;">TIPO</td>
            <td style="padding: 2px; font-size: 6pt; font-weight: bold; text-align: left; width: 25%;">DESCRIPCIÓN</td>
            <td style="padding: 2px; font-size: 6pt; font-weight: bold; text-align: center; width: 15%;">HORARIO</td>
            <td style="padding: 2px; font-size: 6pt; font-weight: bold; text-align: right; width: 40%;">PRESUPUESTO</td>
        </tr>
        @forelse($servicios as $i => $srv)
        <tr style="border-bottom: 1px solid #059669; background-color: {{ $i % 2 === 0 ? '#fafafa' : '#fff' }};">
            <td style="padding: 2px; font-size: 6pt; text-align: center; font-weight: bold; color: #059669;">{{ $srv['tipo'] }}</td>
            <td style="padding: 2px; font-size: 6pt;">{{ $srv['descripcion'] }}</td>
            <td style="padding: 2px; font-size: 6pt; text-align: center;">{{ $srv['horario'] }}</td>
            <td style="padding: 2px; font-size: 6pt; text-align: right; font-weight: bold; color: #059669;">{{ $srv['presupuesto'] }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="4" style="padding: 4px; text-align: center; font-style: italic; color: #666; font-size: 6pt;">Sin servicios registrados</td>
        </tr>
        @endforelse
    </table>

    {{-- Observaciones --}}
    @if($requerimiento->observaciones)
    <table class="border-theme-box bg-theme-light" style="width: 100%; margin-bottom: 8px; padding: 4px 6px; font-size: 6.5pt;">
        <tr>
            <td>
                <span style="font-weight: bold;">Obs:</span> {{ $requerimiento->observaciones }}
            </td>
        </tr>
    </table>
    @endif

    {{-- Firmas --}}
    <table style="width: 100%; margin-top: 12px;">
        <tr>
            <td style="width: 33%; text-align: center; vertical-align: bottom; font-size: 6pt;">
                <div style="border-top: 1px solid #000; margin-top: 16px; padding-top: 2px;">Responsable</div>
            </td>
            <td style="width: 33%; text-align: center; vertical-align: bottom; font-size: 6pt;">
                <div style="border-top: 1px solid #000; margin-top: 16px; padding-top: 2px;">Solicitante</div>
            </td>
            <td style="width: 33%; text-align: center; vertical-align: bottom; font-size: 6pt;">
                <div style="border-top: 1px solid #000; margin-top: 16px; padding-top: 2px;">Supervisor</div>
            </td>
        </tr>
    </table>
@endsection