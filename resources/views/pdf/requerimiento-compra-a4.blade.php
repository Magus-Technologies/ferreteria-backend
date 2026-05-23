@extends('pdf.layout.document')

@section('styles')
    .bg-theme-light { background-color: #fffbeb; }
    .border-theme-box {
        border: 1.5px solid #fadc06;
        border-radius: 4px;
    }
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
                <div style="width: 280px; margin-left: auto; border: 2px solid #fadc06; border-radius: 8px; overflow: hidden; background-color: #fffbeb;">
                    <div style="text-align: center; font-size: 11pt; font-weight: bold; background-color: #fadc06; padding: 6px 8px; border-radius: 4px;">
                        ORDEN DE COMPRA
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
    <div style="font-size: 9pt; font-weight: bold; text-transform: uppercase; margin-bottom: 6px; padding-bottom: 4px; border-bottom: 1px solid #fadc06;">
        Información General
    </div>
    <table class="border-theme-box bg-theme-light" style="width: 100%; font-size: 8pt; margin-bottom: 14px; padding: 6px 10px;">
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
            <td style="font-size: 8pt; padding: 2px;">Orden de Compra</td>
            <td style="font-weight: bold; text-transform: uppercase; font-size: 7.5pt; padding: 2px;">ESTADO:</td>
            <td style="font-size: 8pt; padding: 2px;">{{ $requerimiento->estado }}</td>
        </tr>
    </table>

    {{-- Solicitante y Responsable --}}
    <div style="font-size: 9pt; font-weight: bold; text-transform: uppercase; margin-bottom: 6px; padding-bottom: 4px; border-bottom: 1px solid #fadc06;">
        Solicitante y Responsable
    </div>
    <table class="border-theme-box bg-theme-light" style="width: 100%; font-size: 8pt; margin-bottom: 14px; padding: 6px 10px;">
        <tr>
            <td style="width: 20%; font-weight: bold; text-transform: uppercase; font-size: 7.5pt; padding: 2px;">SOLICITANTE:</td>
            <td style="width: 30%; font-size: 8pt; padding: 2px;">{{ $requerimiento->user->name ?? '—' }}</td>
            <td style="width: 20%; font-weight: bold; text-transform: uppercase; font-size: 7.5pt; padding: 2px;">RESPONSABLE:</td>
            <td style="width: 30%; font-size: 8pt; padding: 2px;">—</td>
        </tr>
    </table>

    {{-- Tabla de Productos --}}
    <div style="font-size: 9pt; font-weight: bold; text-transform: uppercase; margin-bottom: 6px; padding-bottom: 4px; border-bottom: 1px solid #fadc06;">
        Productos Requeridos
    </div>
    @php
        $columnasSeleccionadas = $columnas ?? ['codigo', 'cantidad', 'unidad', 'descripcion'];
        $colCount = count($columnasSeleccionadas);
        $widthAuto = 100 - (in_array('codigo', $columnasSeleccionadas) ? 12 : 0)
                         - (in_array('cantidad', $columnasSeleccionadas) ? 15 : 0)
                         - (in_array('unidad', $columnasSeleccionadas) ? 13 : 0);
    @endphp
    <table style="width: 100%; border: 1.5px solid #fadc06; border-radius: 4px; overflow: hidden; margin-bottom: 14px;">
        <tr style="background-color: #fadc06;">
            @if(in_array('codigo', $columnasSeleccionadas))
                <td style="padding: 4px; font-size: 8pt; font-weight: bold; text-align: center; width: 12%;">CÓDIGO</td>
            @endif
            @if(in_array('cantidad', $columnasSeleccionadas))
                <td style="padding: 4px; font-size: 8pt; font-weight: bold; text-align: center; width: 15%;">CANTIDAD</td>
            @endif
            @if(in_array('unidad', $columnasSeleccionadas))
                <td style="padding: 4px; font-size: 8pt; font-weight: bold; text-align: center; width: 13%;">U.M</td>
            @endif
            @if(in_array('descripcion', $columnasSeleccionadas))
                <td style="padding: 4px; font-size: 8pt; font-weight: bold; text-align: left; width: {{ $widthAuto }}%;">DESCRIPCIÓN</td>
            @endif
        </tr>
        @forelse($productos as $i => $item)
        <tr style="border-bottom: 1px solid #fadc06; background-color: {{ $i % 2 === 0 ? '#fafafa' : '#fff' }};">
            @if(in_array('codigo', $columnasSeleccionadas))
                <td style="padding: 3px 4px; font-size: 8pt; text-align: center;">{{ $item['codigo'] }}</td>
            @endif
            @if(in_array('cantidad', $columnasSeleccionadas))
                <td style="padding: 3px 4px; font-size: 8pt; text-align: center;">{{ $item['cantidad'] }}</td>
            @endif
            @if(in_array('unidad', $columnasSeleccionadas))
                <td style="padding: 3px 4px; font-size: 8pt; text-align: center;">{{ $item['unidad'] }}</td>
            @endif
            @if(in_array('descripcion', $columnasSeleccionadas))
                <td style="padding: 3px 4px; font-size: 8pt;">{{ $item['descripcion'] }}</td>
            @endif
        </tr>
        @empty
        <tr>
            <td colspan="{{ $colCount }}" style="padding: 8px; text-align: center; font-style: italic; color: #666;">Sin productos registrados</td>
        </tr>
        @endforelse
    </table>

    {{-- Observaciones --}}
    <table class="border-theme-box bg-theme-light" style="width: 100%; margin-bottom: 14px; padding: 8px 10px; font-size: 8pt;">
        <tr>
            <td>
                <span style="font-weight: bold;">Observaciones:</span><br>
                {{ $requerimiento->observaciones ?: '—' }}
            </td>
        </tr>
    </table>

    {{-- Proveedor Sugerido --}}
    @if($requerimiento->proveedorSugerido)
    <div style="font-size: 9pt; font-weight: bold; text-transform: uppercase; margin-bottom: 6px; padding-bottom: 4px; border-bottom: 1px solid #fadc06;">
        Proveedor Sugerido
    </div>
    <table class="border-theme-box bg-theme-light" style="width: 100%; font-size: 8pt; margin-bottom: 14px; padding: 6px 10px;">
        <tr>
            <td style="width: 20%; font-weight: bold; text-transform: uppercase; font-size: 7.5pt; padding: 2px;">RAZÓN SOCIAL:</td>
            <td style="width: 30%; font-size: 8pt; padding: 2px;">{{ $requerimiento->proveedorSugerido->razon_social }}</td>
            <td style="width: 20%; font-weight: bold; text-transform: uppercase; font-size: 7.5pt; padding: 2px;">RUC:</td>
            <td style="width: 30%; font-size: 8pt; padding: 2px;">{{ $requerimiento->proveedorSugerido->ruc }}</td>
        </tr>
    </table>
    @endif

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
    <table style="width: 100%; margin-top: 16px; border-top: 2px solid #fadc06; padding-top: 10px;">
        <tr>
            <td style="width: 33%; text-align: center; font-size: 7pt; vertical-align: top;">
                <div style="font-weight: bold; margin-bottom: 3px; text-transform: uppercase; font-size: 7.5pt;">Elaborado Por:</div>
                <div>Área de Compras</div>
            </td>
            <td style="width: 33%; text-align: center; font-size: 7pt; vertical-align: top; border-left: 1.5px solid #fadc06; padding-left: 8px;">
                <div style="font-weight: bold; margin-bottom: 3px; text-transform: uppercase; font-size: 7.5pt;">Revisado Por:</div>
                <div>Departamento de Logística y Operaciones</div>
            </td>
            <td style="width: 33%; text-align: center; font-size: 7pt; vertical-align: top; border-left: 1.5px solid #fadc06; padding-left: 8px;">
                <div style="font-weight: bold; margin-bottom: 3px; text-transform: uppercase; font-size: 7.5pt;">Aprobado por:</div>
                <div>Gerencia General</div>
            </td>
        </tr>
    </table>
@endsection