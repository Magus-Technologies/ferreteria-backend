<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $titulo ?? 'Orden de Compra' }}</title>
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
        <div style="font-weight:bold; font-size:9pt;">ORDEN DE COMPRA</div>
        <div style="font-weight:bold; font-size:9pt;">{{ $requerimiento->codigo }}</div>
        <div style="font-size:6pt;">LOG-F-03</div>
    </div>

    <div class="separator"></div>

    {{-- Info general --}}
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
        </table>
    </div>

    <div class="separator"></div>

    {{-- Tabla de productos --}}
    <div style="padding-top: 4px;">
        <table>
            <thead>
                <tr style="border-bottom: 1px solid #000;">
                    <th style="text-align:center; width:20%; font-weight:bold; font-size:6pt;">Cód.</th>
                    <th style="text-align:center; width:15%; font-weight:bold; font-size:6pt;">Cant</th>
                    <th style="text-align:center; width:15%; font-weight:bold; font-size:6pt;">U.M</th>
                    <th style="text-align:left; font-weight:bold; font-size:6pt;">Descripción</th>
                </tr>
            </thead>
            <tbody>
                @forelse($productos as $i => $item)
                <tr style="border-bottom: 1px solid #000;">
                    <td style="padding:2px 0; text-align:center; font-size:6pt;">{{ $item['codigo'] }}</td>
                    <td style="padding:2px 0; text-align:center; font-size:6pt;">{{ $item['cantidad'] }}</td>
                    <td style="padding:2px 0; text-align:center; font-size:6pt;">{{ $item['unidad'] }}</td>
                    <td style="padding:2px 0; font-size:6pt;">{{ $item['descripcion'] }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" style="padding:4px; text-align:center; font-style:italic; color:#666; font-size:6pt;">Sin productos registrados</td>
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

    {{-- Proveedor sugerido --}}
    @if($requerimiento->proveedorSugerido)
    <div class="separator"></div>
    <div style="margin-top: 4px;">
        <table>
            <tr>
                <td style="text-transform:uppercase; font-weight:bold; font-size:6pt; width:40%;">Proveedor:</td>
                <td style="font-size:6pt;">{{ $requerimiento->proveedorSugerido->razon_social }}</td>
            </tr>
            <tr>
                <td style="text-transform:uppercase; font-weight:bold; font-size:6pt;">RUC:</td>
                <td style="font-size:6pt;">{{ $requerimiento->proveedorSugerido->ruc }}</td>
            </tr>
        </table>
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
